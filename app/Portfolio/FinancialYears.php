<?php

namespace App\Portfolio;

/**
 * Money invested and redeemed in each Indian financial year (April to March).
 */
final class FinancialYears
{
    /**
     * @param  list<Movement>  $movements
     * @return list<array{label: string, invested_paise: int, redeemed_paise: int}> Oldest first
     */
    public function of(array $movements): array
    {
        $years = [];

        foreach ($movements as $movement) {
            $start = $movement->date->month >= 4 ? $movement->date->year : $movement->date->year - 1;
            $years[$start] ??= ['label' => self::label($start), 'invested_paise' => 0, 'redeemed_paise' => 0];
            $years[$start][$movement->addsUnits ? 'invested_paise' : 'redeemed_paise'] += $movement->amountPaise;
        }

        ksort($years);

        return array_values($years);
    }

    /** 2025 → "FY 2025-26". */
    private static function label(int $startYear): string
    {
        return sprintf('FY %d-%02d', $startYear, ($startYear + 1) % 100);
    }
}
