<?php

namespace Tests\Unit\Portfolio;

use App\Portfolio\FundStats;
use App\Portfolio\FundStatsResult;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class FundStatsTest extends TestCase
{
    private const TOLERANCE = 1e-9;

    public function test_trailing_returns_compound_annually()
    {
        $stats = $this->stats(self::longHistory());

        $this->assertSame('2026-10-07', $stats->asOf);
        $this->assertSame('2021-01-01', $stats->since);
        $this->assertEqualsWithDelta(0.25, $stats->return1y, self::TOLERANCE);            // 16 → 20
        $this->assertEqualsWithDelta(2 ** (1 / 3) - 1, $stats->cagr3y, self::TOLERANCE);  // 10 → 20
        $this->assertEqualsWithDelta(1.6 ** (1 / 5) - 1, $stats->cagr5y, self::TOLERANCE); // 12.5 → 20
    }

    public function test_return_since_the_first_nav_is_annualised_after_a_year()
    {
        $stats = $this->stats(self::longHistory());

        // 8 → 20 over 2,105 days.
        $this->assertTrue($stats->sinceStartAnnualised);
        $this->assertEqualsWithDelta(2.5 ** (365 / 2105) - 1, $stats->sinceStart, self::TOLERANCE);
    }

    public function test_max_drawdown_is_the_largest_fall_from_a_peak()
    {
        $stats = $this->stats(self::longHistory());

        // 15 on 1 Jun 2022 down to 9 on 1 Mar 2023; later lows are shallower.
        $this->assertEqualsWithDelta(0.4, $stats->maxDrawdown, self::TOLERANCE);
        $this->assertSame('2022-06-01', $stats->drawdownPeak);
        $this->assertSame('2023-03-01', $stats->drawdownTrough);
    }

    public function test_a_nav_that_never_falls_has_no_drawdown()
    {
        $stats = $this->stats(['2026-01-01' => '10.0000', '2026-02-01' => '10.5000', '2026-03-01' => '11.0000']);

        $this->assertSame(0.0, $stats->maxDrawdown);
        $this->assertNull($stats->drawdownPeak);
        $this->assertNull($stats->drawdownTrough);
    }

    public function test_volatility_annualises_the_spread_of_weekly_returns()
    {
        // 53 weekly NAVs alternating 10 and 11: 26 returns of +10% and 26 of −1/11.
        $navs = [];
        $end = CarbonImmutable::parse('2026-10-07');
        for ($week = 52; $week >= 0; $week--) {
            $navs[$end->subWeeks($week)->toDateString()] = $week % 2 === 0 ? '10.0000' : '11.0000';
        }

        $deviation = (0.1 + 1 / 11) / 2;
        $expected = $deviation * sqrt(52 / 51) * sqrt(52);

        $this->assertEqualsWithDelta($expected, $this->stats($navs)->volatility, 1e-6);
    }

    public function test_steady_growth_has_almost_no_volatility()
    {
        $navs = [];
        $nav = '10.0000';
        for ($date = CarbonImmutable::parse('2025-01-01'); $date <= CarbonImmutable::parse('2026-10-07'); $date = $date->addDay()) {
            $navs[$date->toDateString()] = $nav;
            $nav = bcadd($nav, '0.0100', 4);
        }

        $this->assertLessThan(0.01, $this->stats($navs)->volatility);
    }

    public function test_a_short_history_has_no_trailing_returns_and_an_absolute_return()
    {
        $stats = $this->stats(['2026-04-01' => '10.0000', '2026-10-07' => '11.0000']);

        $this->assertNull($stats->return1y);
        $this->assertNull($stats->cagr3y);
        $this->assertNull($stats->cagr5y);
        $this->assertNull($stats->volatility);
        $this->assertFalse($stats->sinceStartAnnualised);
        $this->assertEqualsWithDelta(0.1, $stats->sinceStart, self::TOLERANCE);
    }

    public function test_a_trailing_period_uses_the_nav_on_or_before_its_start()
    {
        // 7 Oct 2025 had no NAV; 6 Oct's applies.
        $stats = $this->stats(['2025-10-06' => '10.0000', '2025-10-08' => '50.0000', '2026-10-07' => '12.0000']);

        $this->assertEqualsWithDelta(0.2, $stats->return1y, self::TOLERANCE);
    }

    public function test_too_little_history_gives_no_stats()
    {
        $this->assertNull((new FundStats)->of([]));
        $this->assertNull((new FundStats)->of(['2026-10-07' => '10.0000']));
        $this->assertNull((new FundStats)->of(['2026-10-06' => '0.0000', '2026-10-07' => '10.0000']));
    }

    public function test_the_array_form_round_trips()
    {
        $stats = $this->stats(self::longHistory());
        $restored = FundStatsResult::fromArray($stats->toArray());

        $this->assertSame($stats->toArray(), $restored->toArray());
        $this->assertSame(25.0, $stats->toArray()['return_1y_pct']);
        $this->assertSame(40.0, $stats->toArray()['max_drawdown_pct']);
    }

    /**
     * @param  array<string, string>  $navs
     */
    private function stats(array $navs): FundStatsResult
    {
        $stats = (new FundStats)->of($navs);
        $this->assertNotNull($stats);

        return $stats;
    }

    /**
     * @return array<string, string>
     */
    private static function longHistory(): array
    {
        return [
            '2021-01-01' => '8.0000',
            '2021-10-07' => '12.5000',
            '2022-06-01' => '15.0000',
            '2023-03-01' => '9.0000',
            '2023-10-07' => '10.0000',
            '2025-10-07' => '16.0000',
            '2026-10-07' => '20.0000',
        ];
    }
}
