<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum ChangePeriod: string
{
    case Week = '7d';
    case Month = '30d';
    case MonthToDate = 'month';

    /**
     * The valuation date the period is measured from, for a period ending on the given date.
     */
    public function startFor(CarbonImmutable $end): CarbonImmutable
    {
        return match ($this) {
            self::Week => $end->subDays(7),
            self::Month => $end->subDays(30),
            // Closing value of the previous month.
            self::MonthToDate => $end->startOfMonth()->subDay(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Week => 'last 7 days',
            self::Month => 'last 30 days',
            self::MonthToDate => 'this month so far',
        };
    }
}
