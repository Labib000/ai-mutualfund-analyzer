<?php

namespace App\Portfolio;

use Carbon\CarbonImmutable;

/**
 * Works out the dates a SIP installment falls due.
 */
final class SipSchedule
{
    /**
     * Installment dates after $after (exclusive) up to $until (inclusive), within
     * the SIP's start and end dates. Days 29–31 fall on the last day of shorter months.
     *
     * @param  int  $day  Day of month, 1–31
     * @return list<CarbonImmutable>
     */
    public function dueDates(
        int $day,
        CarbonImmutable $start,
        ?CarbonImmutable $end,
        ?CarbonImmutable $after,
        CarbonImmutable $until,
    ): array {
        $start = $start->startOfDay();
        $until = $until->startOfDay();
        $last = $end === null ? $until : $end->startOfDay()->min($until);
        $after = $after?->startOfDay();

        $from = $after === null ? $start : $start->max($after);
        $month = $from->startOfMonth();
        $dates = [];

        while ($month->lessThanOrEqualTo($last)) {
            $date = $month->setDay(min($day, $month->daysInMonth));

            if ($date->greaterThanOrEqualTo($start)
                && $date->lessThanOrEqualTo($last)
                && ($after === null || $date->greaterThan($after))) {
                $dates[] = $date;
            }

            $month = $month->addMonthNoOverflow();
        }

        return $dates;
    }
}
