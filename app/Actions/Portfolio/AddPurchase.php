<?php

namespace App\Actions\Portfolio;

use App\Enums\TransactionType;
use App\Models\Holding;
use App\Models\Transaction;
use App\Portfolio\UnitCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class AddPurchase
{
    public function __construct(
        private readonly ResolveTransactionNav $resolveNav,
        private readonly UnitCalculator $calculator,
    ) {}

    /**
     * Record a lump-sum purchase. Units are calculated from the applicable NAV
     * after stamp duty, unless the user supplies them from their statement.
     *
     * @param  numeric-string|null  $unitsOverride
     *
     * @throws ValidationException
     */
    public function handle(Holding $holding, CarbonImmutable $date, int $amountPaise, ?string $unitsOverride = null): Transaction
    {
        $point = $this->resolveNav->handle($holding->scheme, $date);

        $stampDuty = $this->calculator->stampDuty($amountPaise);
        $units = $unitsOverride ?? $this->calculator->purchaseUnits($amountPaise, $stampDuty, $point->nav);

        if (bccomp($units, '0', UnitCalculator::UNITS_SCALE) <= 0) {
            throw ValidationException::withMessages([
                'amount' => "This amount buys less than 0.001 units at the NAV of ₹{$point->nav}.",
            ]);
        }

        return $holding->transactions()->create([
            'type' => TransactionType::Purchase,
            'txn_date' => $date,
            'nav_date' => $point->date,
            'nav' => $point->nav,
            'amount_paise' => $amountPaise,
            'stamp_duty_paise' => $stampDuty,
            'units' => $units,
            'units_overridden' => $unitsOverride !== null,
        ]);
    }
}
