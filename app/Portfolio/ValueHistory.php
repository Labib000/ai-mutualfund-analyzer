<?php

namespace App\Portfolio;

use App\Support\Decimal;

/**
 * Portfolio value and invested amount (FIFO cost of units held) over time,
 * sampled weekly back from the valuation date.
 */
final class ValueHistory
{
    private const STEP_DAYS = 7;

    public function __construct(private readonly UnitCalculator $units = new UnitCalculator) {}

    /**
     * @param  list<array{HoldingInput, array<string, string>}>  $holdings  Each fund with its NAVs keyed by Y-m-d
     * @return list<array{date: string, value_paise: int, invested_paise: int}>
     */
    public function series(array $holdings): array
    {
        $dates = $this->sampleDates($holdings);

        if ($dates === []) {
            return [];
        }

        $values = array_fill_keys($dates, 0);
        $invested = array_fill_keys($dates, 0);

        foreach ($holdings as [$holding, $navs]) {
            foreach ($this->holdingSeries($holding, $navs, $dates) as $date => [$value, $cost]) {
                $values[$date] += $value;
                $invested[$date] += $cost;
            }
        }

        return array_map(
            fn (string $date) => ['date' => $date, 'value_paise' => $values[$date], 'invested_paise' => $invested[$date]],
            $dates,
        );
    }

    /**
     * Weekly dates ending on the newest NAV date, starting at the first transaction.
     *
     * @param  list<array{HoldingInput, array<string, string>}>  $holdings
     * @return list<string>
     */
    private function sampleDates(array $holdings): array
    {
        $first = null;
        $last = null;

        foreach ($holdings as [$holding]) {
            foreach ($holding->movements as $movement) {
                $first = $first === null ? $movement->date : $first->min($movement->date);
            }

            if ($holding->latestNavDate !== null && $holding->movements !== []) {
                $last = $last === null ? $holding->latestNavDate : $last->max($holding->latestNavDate);
            }
        }

        if ($first === null || $last === null || $last->lessThan($first)) {
            return [];
        }

        $first = $first->startOfDay();
        $dates = [$first->toDateString()];

        for ($date = $last->startOfDay(); $date->greaterThan($first); $date = $date->subDays(self::STEP_DAYS)) {
            $dates[] = $date->toDateString();
        }

        sort($dates);

        return array_values(array_unique($dates));
    }

    /**
     * Value and FIFO cost of one fund on each sample date, walking forward once.
     *
     * @param  array<string, string>  $navs
     * @param  list<string>  $dates  Ascending
     * @return array<string, array{int, int}>
     */
    private function holdingSeries(HoldingInput $holding, array $navs, array $dates): array
    {
        $movements = $holding->movements;
        // Same-day purchases come before redemptions, as everywhere else.
        usort($movements, fn (Movement $a, Movement $b) => [$a->day, ! $a->addsUnits] <=> [$b->day, ! $b->addsUnits]);
        ksort($navs);
        $navDates = array_keys($navs);

        $lots = new FifoLots;
        $nextMovement = 0;
        $nextNav = 0;
        $nav = null;
        $series = [];

        foreach ($dates as $date) {
            while ($nextMovement < count($movements) && $movements[$nextMovement]->day <= $date) {
                $movement = $movements[$nextMovement++];
                $movement->addsUnits
                    ? $lots->buy($movement->units, $movement->amountPaise)
                    : $lots->sell($movement->units, $movement->amountPaise);
            }

            while ($nextNav < count($navDates) && $navDates[$nextNav] <= $date) {
                $nav = $navs[$navDates[$nextNav++]];
            }

            $units = $lots->unitsHeld();
            $value = $nav !== null && Decimal::isNumeric($nav) && bccomp($units, '0', UnitCalculator::UNITS_SCALE) > 0
                ? $this->units->redemptionAmount($units, $nav)
                : 0;

            $series[$date] = [$value, $lots->costPaise()];
        }

        return $series;
    }
}
