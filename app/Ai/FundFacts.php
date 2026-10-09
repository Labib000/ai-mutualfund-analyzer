<?php

namespace App\Ai;

use App\Portfolio\FundStatsResult;
use Carbon\CarbonImmutable;

/**
 * A scheme's past returns and risk as the AI model sees them.
 */
final class FundFacts
{
    /**
     * Every figure, one per line, for the fund explainer.
     */
    public static function full(FundStatsResult $stats): string
    {
        $lines = [
            'Past NAV returns up to '.self::date($stats->asOf).': '.self::returns($stats, withSince: true).'.',
        ];

        if ($stats->volatility !== null) {
            $lines[] = 'Volatility (annualised spread of weekly returns, last 3 years or less): '.self::pct($stats->volatility).'.';
        }

        $lines[] = $stats->drawdownPeak === null
            ? 'Largest fall from a peak since '.self::date($stats->since).': none.'
            : 'Largest fall from a peak since '.self::date($stats->since).': −'.self::pct($stats->maxDrawdown)
                .' (from '.self::date($stats->drawdownPeak).' to '.self::date((string) $stats->drawdownTrough).').';

        return implode("\n", $lines);
    }

    /**
     * The headline figures in one short phrase, for a fund line in the portfolio data.
     */
    public static function short(FundStatsResult $stats): string
    {
        $parts = ['fund NAV return '.self::returns($stats, withSince: false)];

        if ($stats->volatility !== null) {
            $parts[] = 'volatility '.self::pct($stats->volatility);
        }

        if ($stats->drawdownPeak !== null) {
            $parts[] = 'largest fall −'.self::pct($stats->maxDrawdown);
        }

        return implode(', ', $parts);
    }

    private static function returns(FundStatsResult $stats, bool $withSince): string
    {
        $parts = [];

        if ($stats->return1y !== null) {
            $parts[] = '1 year '.self::signed($stats->return1y);
        }

        if ($stats->cagr3y !== null) {
            $parts[] = '3 years '.self::signed($stats->cagr3y).' a year';
        }

        if ($stats->cagr5y !== null) {
            $parts[] = '5 years '.self::signed($stats->cagr5y).' a year';
        }

        if ($withSince || $parts === []) {
            $parts[] = 'since '.self::date($stats->since).' '.self::signed($stats->sinceStart)
                .($stats->sinceStartAnnualised ? ' a year' : ' in total (less than a year of history)');
        }

        return implode('; ', $parts);
    }

    private static function signed(float $fraction): string
    {
        $rounded = round($fraction * 100, 1);

        return ($rounded > 0 ? '+' : ($rounded < 0 ? '−' : '')).number_format(abs($rounded), 1).'%';
    }

    private static function pct(float $fraction): string
    {
        return number_format($fraction * 100, 1).'%';
    }

    private static function date(string $date): string
    {
        return CarbonImmutable::parse($date)->format('j M Y');
    }
}
