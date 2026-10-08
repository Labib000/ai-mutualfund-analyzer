<?php

namespace App\Actions\Portfolio;

use App\Enums\TransactionType;
use App\Models\Sip;
use App\Nav\NavLookup;
use App\Nav\NavProviderException;
use App\Portfolio\SipSchedule;
use App\Portfolio\UnitCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GenerateSipInstallments
{
    public function __construct(
        private readonly NavLookup $navs,
        private readonly SipSchedule $schedule,
        private readonly UnitCalculator $calculator,
    ) {}

    /**
     * Record every installment due up to today whose NAV is published, oldest
     * first. Stops at the first installment still waiting for its NAV; the next
     * run picks it up. Safe to run repeatedly.
     *
     * @return int The number of installments created
     *
     * @throws NavProviderException when the scheme's NAV history can't be fetched
     */
    public function handle(Sip $sip, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $scheme = $sip->holding->scheme;

        // Network calls happen here, before any locks are taken.
        $this->navs->ensureHistory($scheme);

        return DB::transaction(function () use ($sip, $scheme, $today) {
            /** @var Sip $sip */
            $sip = Sip::query()->whereKey($sip->getKey())->lockForUpdate()->firstOrFail();

            $dueDates = $this->schedule->dueDates($sip->day_of_month, $sip->start_date, $sip->end_date, $sip->generated_until, $today);
            $created = 0;

            foreach ($dueDates as $date) {
                $point = $this->navs->forTransaction($scheme, $date);

                if ($point === null) {
                    break;
                }

                $stampDuty = $this->calculator->stampDuty($sip->amount_paise);

                $sip->holding->transactions()->create([
                    'sip_id' => $sip->id,
                    'type' => TransactionType::SipInstallment,
                    'txn_date' => $date,
                    'nav_date' => $point->date,
                    'nav' => $point->nav,
                    'amount_paise' => $sip->amount_paise,
                    'stamp_duty_paise' => $stampDuty,
                    'units' => $this->calculator->purchaseUnits($sip->amount_paise, $stampDuty, $point->nav),
                    'units_overridden' => false,
                ]);

                $sip->generated_until = $date;
                $created++;
            }

            $sip->save();

            return $created;
        });
    }
}
