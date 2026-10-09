<?php

namespace App\Portfolio;

use App\Enums\ChangePeriod;
use App\Support\Decimal;
use Carbon\CarbonImmutable;

/**
 * Splits a portfolio's change in value over a period into new money (net
 * purchases and redemptions) and market movement (everything else).
 *
 * Values use the latest NAV on or before each end of the period, as everywhere
 * else in valuation; movements on the start date belong to the start value.
 */
final class PortfolioChange
{
    public function __construct(private readonly UnitCalculator $units = new UnitCalculator) {}

    /**
     * @param  list<array{string, HoldingInput, array<string, string>}>  $funds  Name, movements and NAVs keyed by Y-m-d
     * @param  int  $sipInstallments  Installments recorded in the period, counted by the caller
     */
    public function over(ChangePeriod $period, CarbonImmutable $end, array $funds, int $sipInstallments = 0): PortfolioChangeResult
    {
        $to = $end->toDateString();
        $from = $period->startFor($end)->toDateString();
        $changes = [];

        foreach ($funds as [$name, $holding, $navs]) {
            ksort($navs);
            $start = $this->value($holding, $navs, $from);
            $finish = $this->value($holding, $navs, $to);
            $flow = 0;

            foreach ($holding->movements as $movement) {
                if ($movement->day > $from && $movement->day <= $to) {
                    $flow += $movement->addsUnits ? $movement->amountPaise : -$movement->amountPaise;
                }
            }

            if ($start !== 0 || $finish !== 0 || $flow !== 0) {
                $changes[] = new ChangeFund($name, $start, $finish, $flow);
            }
        }

        usort($changes, fn (ChangeFund $a, ChangeFund $b) => $b->endPaise <=> $a->endPaise);

        return new PortfolioChangeResult($period, $from, $to, $changes, $sipInstallments);
    }

    /**
     * Units held at the end of the date, at the latest NAV on or before it.
     *
     * @param  array<string, string>  $navs  Sorted by date
     */
    private function value(HoldingInput $holding, array $navs, string $date): int
    {
        $units = '0';

        foreach ($holding->movements as $movement) {
            if ($movement->day <= $date) {
                $units = $movement->addsUnits
                    ? bcadd($units, $movement->units, UnitCalculator::UNITS_SCALE)
                    : bcsub($units, $movement->units, UnitCalculator::UNITS_SCALE);
            }
        }

        $nav = null;
        foreach ($navs as $navDate => $candidate) {
            if ((string) $navDate > $date) {
                break;
            }

            $nav = $candidate;
        }

        if ($nav === null || ! Decimal::isNumeric($nav) || bccomp($units, '0', UnitCalculator::UNITS_SCALE) <= 0) {
            return 0;
        }

        return $this->units->redemptionAmount($units, $nav);
    }
}
