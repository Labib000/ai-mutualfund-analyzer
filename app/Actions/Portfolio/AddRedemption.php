<?php

namespace App\Actions\Portfolio;

use App\Enums\TransactionType;
use App\Models\Holding;
use App\Models\Transaction;
use App\Portfolio\UnitCalculator;
use App\Portfolio\UnitLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddRedemption
{
    public function __construct(
        private readonly ResolveTransactionNav $resolveNav,
        private readonly UnitCalculator $calculator,
    ) {}

    /**
     * Record a redemption of the given units, or of every unit held on the date
     * when $units is null. The amount received defaults to units × NAV.
     *
     * @param  numeric-string|null  $units
     *
     * @throws ValidationException
     */
    public function handle(Holding $holding, CarbonImmutable $date, ?string $units, ?int $amountPaise = null): Transaction
    {
        // Fetch the NAV before taking the lock: it may call the NAV provider.
        $point = $this->resolveNav->handle($holding->scheme, $date);

        return DB::transaction(function () use ($holding, $date, $units, $amountPaise, $point) {
            LockHolding::handle($holding);

            $ledger = UnitLedger::fromTransactions($holding->transactions()->get());
            $held = $ledger->unitsHeldOn($date->toDateString());
            $units ??= $held;

            if (bccomp($units, '0', UnitCalculator::UNITS_SCALE) <= 0) {
                throw ValidationException::withMessages([
                    'units' => "You held no units on {$date->format('j M Y')}.",
                ]);
            }

            $overdrawn = $ledger->with($date->toDateString(), $units, addsUnits: false)->firstOverdrawnDate();

            if ($overdrawn !== null) {
                throw ValidationException::withMessages([
                    'units' => $overdrawn === $date->toDateString()
                        ? "You held only {$held} units on {$date->format('j M Y')}."
                        : 'This would leave the redemption on '.CarbonImmutable::parse($overdrawn)->format('j M Y').' with more units than you held.',
                ]);
            }

            return $holding->transactions()->create([
                'type' => TransactionType::Redemption,
                'txn_date' => $date,
                'nav_date' => $point->date,
                'nav' => $point->nav,
                'amount_paise' => $amountPaise ?? $this->calculator->redemptionAmount($units, $point->nav),
                'stamp_duty_paise' => 0,
                'units' => $units,
                'units_overridden' => false,
            ]);
        });
    }
}
