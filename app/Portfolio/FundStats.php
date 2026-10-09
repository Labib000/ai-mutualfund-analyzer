<?php

namespace App\Portfolio;

use Carbon\CarbonImmutable;

/**
 * Trailing returns, volatility and the worst fall of a scheme, from its NAVs.
 *
 * NAVs stay bcmath strings; only the ratios between them become floats, since
 * roots and square roots of a rate don't need exact decimals (as in Xirr).
 */
final class FundStats
{
    private const SCALE = 12;

    /** Volatility is measured over the last three years of weekly returns. */
    private const VOLATILITY_YEARS = 3;

    /** Fewer weekly returns than half a year say little about volatility. */
    private const MIN_WEEKLY_RETURNS = 26;

    /**
     * @param  array<string, string>  $navs  NAVs keyed by Y-m-d date
     */
    public function of(array $navs): ?FundStatsResult
    {
        $navs = array_filter($navs, fn (string $nav) => bccomp(self::numeric($nav), '0', 4) === 1);
        ksort($navs);

        if (count($navs) < 2) {
            return null;
        }

        $dates = array_keys($navs);
        $first = $dates[0];
        $last = $dates[count($dates) - 1];
        $end = CarbonImmutable::parse($last);
        $years = CarbonImmutable::parse($first)->diffInDays($end) / 365;

        $sinceStart = $this->ratio($navs[$last], $navs[$first]);
        $annualised = $years >= 1;

        [$drawdown, $peak, $trough] = $this->maxDrawdown($navs);

        return new FundStatsResult(
            asOf: $last,
            since: $first,
            return1y: $this->trailing($navs, $dates, $end, 1),
            cagr3y: $this->trailing($navs, $dates, $end, 3),
            cagr5y: $this->trailing($navs, $dates, $end, 5),
            sinceStart: $annualised ? $sinceStart ** (1 / $years) - 1 : $sinceStart - 1,
            sinceStartAnnualised: $annualised,
            volatility: $this->volatility($navs, $dates, $end),
            maxDrawdown: $drawdown,
            drawdownPeak: $peak,
            drawdownTrough: $trough,
        );
    }

    /**
     * Compound annual return over the last N years, or null when the history is shorter.
     *
     * @param  array<string, string>  $navs
     * @param  list<string>  $dates
     */
    private function trailing(array $navs, array $dates, CarbonImmutable $end, int $years): ?float
    {
        $start = $this->onOrBefore($dates, $end->subYears($years)->toDateString());

        if ($start === null) {
            return null;
        }

        return $this->ratio($navs[$dates[count($dates) - 1]], $navs[$start]) ** (1 / $years) - 1;
    }

    /**
     * Annualised standard deviation of weekly returns, sampled back from the latest NAV.
     *
     * @param  array<string, string>  $navs
     * @param  list<string>  $dates
     */
    private function volatility(array $navs, array $dates, CarbonImmutable $end): ?float
    {
        $from = $end->subYears(self::VOLATILITY_YEARS)->toDateString();
        $weekly = [];

        for ($date = $end; $date->toDateString() >= max($from, $dates[0]); $date = $date->subWeek()) {
            $found = $this->onOrBefore($dates, $date->toDateString());

            if ($found === null) {
                break;
            }

            $weekly[$found] = $navs[$found];
        }

        // Sparse history repeats the same NAV week after week, which would
        // look like calm rather than missing data.
        if (count($weekly) <= self::MIN_WEEKLY_RETURNS) {
            return null;
        }

        $weekly = array_values(array_reverse($weekly));
        $returns = [];

        for ($i = 1; $i < count($weekly); $i++) {
            $returns[] = $this->ratio($weekly[$i], $weekly[$i - 1]) - 1;
        }

        $count = count($returns);
        $mean = array_sum($returns) / $count;
        $variance = array_sum(array_map(fn (float $r) => ($r - $mean) ** 2, $returns)) / ($count - 1);

        return sqrt($variance) * sqrt(52);
    }

    /**
     * The largest fall from a peak to a later low, with both dates. Zero when the NAV never fell.
     *
     * @param  array<string, string>  $navs  Sorted by date
     * @return array{float, ?string, ?string}
     */
    private function maxDrawdown(array $navs): array
    {
        $peakDate = null;
        $peakNav = null;
        $worst = '0';
        $worstPeak = null;
        $worstTrough = null;

        foreach ($navs as $date => $nav) {
            $nav = self::numeric($nav);

            if ($peakNav === null || bccomp($nav, $peakNav, 4) === 1) {
                $peakNav = $nav;
                $peakDate = $date;

                continue;
            }

            $fall = bcsub('1', bcdiv($nav, $peakNav, self::SCALE), self::SCALE);

            if (bccomp($fall, $worst, self::SCALE) === 1) {
                $worst = $fall;
                $worstPeak = $peakDate;
                $worstTrough = (string) $date;
            }
        }

        return [(float) $worst, $worstPeak, $worstTrough];
    }

    /**
     * The latest date in the sorted list on or before the given date.
     *
     * @param  list<string>  $dates
     */
    private function onOrBefore(array $dates, string $date): ?string
    {
        $low = 0;
        $high = count($dates) - 1;
        $found = null;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);

            if ($dates[$mid] <= $date) {
                $found = $dates[$mid];
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $found;
    }

    private function ratio(string $numerator, string $denominator): float
    {
        return (float) bcdiv(self::numeric($numerator), self::numeric($denominator), self::SCALE);
    }

    /**
     * @return numeric-string
     */
    private static function numeric(string $value): string
    {
        return is_numeric($value) ? $value : '0';
    }
}
