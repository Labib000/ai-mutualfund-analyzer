<?php

namespace App\Portfolio;

use App\Enums\XirrStatus;

/**
 * Works out invested amount (FIFO cost of units held), current value, gains
 * and XIRR for a fund or a portfolio.
 */
final class PerformanceCalculator
{
    /** XIRR isn't shown until the first investment is this old. */
    public const MIN_XIRR_DAYS = 30;

    /** Below this, XIRR is annualised from a short period. */
    public const FULL_YEAR_DAYS = 365;

    public function __construct(
        private readonly UnitCalculator $units = new UnitCalculator,
        private readonly Xirr $xirr = new Xirr,
    ) {}

    public function forHolding(HoldingInput $holding): Performance
    {
        [$performance] = $this->evaluate($holding);

        return $performance;
    }

    /**
     * @param  list<HoldingInput>  $holdings
     */
    public function forPortfolio(array $holdings): Performance
    {
        $invested = 0;
        $value = 0;
        $realised = 0;
        $flows = [];
        $valuedOn = null;

        foreach ($holdings as $holding) {
            [$performance, $holdingFlows] = $this->evaluate($holding);

            $invested += $performance->investedPaise;
            $value += $performance->valuePaise;
            $realised += $performance->realisedGainPaise;
            $flows = [...$flows, ...$holdingFlows];

            if ($performance->valuedOn !== null && ($valuedOn === null || $performance->valuedOn->greaterThan($valuedOn))) {
                $valuedOn = $performance->valuedOn;
            }
        }

        [$xirr, $status] = $this->xirr($flows);

        return new Performance(null, $invested, $value, $realised, $xirr, $status, $valuedOn);
    }

    /**
     * @return array{Performance, list<CashFlow>}
     */
    private function evaluate(HoldingInput $holding): array
    {
        $movements = $holding->movements;
        // Same-day purchases come before redemptions, as in UnitLedger.
        usort($movements, fn (Movement $a, Movement $b) => [$a->day, ! $a->addsUnits] <=> [$b->day, ! $b->addsUnits]);

        $lots = new FifoLots;
        $flows = [];

        foreach ($movements as $movement) {
            if ($movement->addsUnits) {
                $lots->buy($movement->units, $movement->amountPaise);
                $flows[] = new CashFlow($movement->date, -$movement->amountPaise);
            } else {
                $lots->sell($movement->units, $movement->amountPaise);
                $flows[] = new CashFlow($movement->date, $movement->amountPaise);
            }
        }

        $unitsHeld = $lots->unitsHeld();
        $value = 0;

        if ($holding->latestNav !== null && bccomp($unitsHeld, '0', UnitCalculator::UNITS_SCALE) > 0) {
            $value = $this->units->redemptionAmount($unitsHeld, $holding->latestNav);
        }

        if ($value > 0 && $holding->latestNavDate !== null) {
            $flows[] = new CashFlow($holding->latestNavDate, $value);
        }

        [$xirr, $status] = $this->xirr($flows);

        $valuedOn = $value > 0 ? $holding->latestNavDate : null;

        return [
            new Performance($unitsHeld, $lots->costPaise(), $value, $lots->realisedGainPaise(), $xirr, $status, $valuedOn),
            $flows,
        ];
    }

    /**
     * @param  list<CashFlow>  $flows
     * @return array{?float, XirrStatus}
     */
    private function xirr(array $flows): array
    {
        if ($flows === []) {
            return [null, XirrStatus::NotMeaningful];
        }

        $days = array_map(fn (CashFlow $flow) => $flow->epochDay, $flows);
        $span = max($days) - min($days);

        if ($span < self::MIN_XIRR_DAYS) {
            return [null, XirrStatus::TooRecent];
        }

        $rate = $this->xirr->rate($flows);

        if ($rate === null) {
            return [null, XirrStatus::NotMeaningful];
        }

        return [$rate, $span < self::FULL_YEAR_DAYS ? XirrStatus::ShortPeriod : XirrStatus::Ok];
    }
}
