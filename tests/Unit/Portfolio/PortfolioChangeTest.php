<?php

namespace Tests\Unit\Portfolio;

use App\Enums\ChangePeriod;
use App\Portfolio\HoldingInput;
use App\Portfolio\Movement;
use App\Portfolio\PortfolioChange;
use App\Portfolio\PortfolioChangeResult;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Week to Wed 7 Oct 2026: the period starts at the close of Wed 30 Sep.
 */
class PortfolioChangeTest extends TestCase
{
    public function test_a_fund_without_new_money_moves_only_with_the_market()
    {
        $change = $this->week([
            ['Steady', [self::buy('2026-09-01', '100', 100000)], ['2026-09-30' => '10.0000', '2026-10-07' => '11.0000']],
        ]);

        $this->assertSame('2026-09-30', $change->from);
        $this->assertSame('2026-10-07', $change->to);
        $this->assertSame(100000, $change->startPaise());
        $this->assertSame(110000, $change->endPaise());
        $this->assertSame(0, $change->netFlowPaise());
        $this->assertSame(10000, $change->marketPaise());
        $this->assertEqualsWithDelta(10.0, $change->marketPct(), 1e-9);
    }

    public function test_new_money_is_separated_from_market_movement()
    {
        $change = $this->week([
            ['Topped up', [self::buy('2026-09-01', '50', 50000), self::buy('2026-10-05', '50', 60000)], [
                '2026-09-30' => '10.0000', '2026-10-05' => '12.0000', '2026-10-07' => '12.5000',
            ]],
        ]);

        $fund = $change->funds[0];
        $this->assertSame(50000, $fund->startPaise);
        $this->assertSame(125000, $fund->endPaise);
        $this->assertSame(60000, $fund->netFlowPaise);
        $this->assertSame(15000, $fund->marketPaise());
        $this->assertEqualsWithDelta(30.0, $fund->marketPct(), 1e-9);
    }

    public function test_redemption_proceeds_count_as_money_taken_out()
    {
        $change = $this->week([
            ['Part sold', [self::buy('2026-09-01', '100', 100000), self::sell('2026-10-06', '40', 44000)], [
                '2026-09-30' => '10.0000', '2026-10-06' => '11.0000', '2026-10-07' => '11.0000',
            ]],
        ]);

        $this->assertSame(66000, $change->endPaise());
        $this->assertSame(-44000, $change->netFlowPaise());
        $this->assertSame(10000, $change->marketPaise());
    }

    public function test_a_fund_bought_during_the_period_has_no_market_percentage()
    {
        $change = $this->week([
            ['New', [self::buy('2026-10-06', '10', 10000)], ['2026-10-06' => '10.0000', '2026-10-07' => '9.0000']],
        ]);

        $fund = $change->funds[0];
        $this->assertSame(0, $fund->startPaise);
        $this->assertSame(-1000, $fund->marketPaise());
        $this->assertNull($fund->marketPct());
        $this->assertNull($change->marketPct());
        $this->assertNull($change->best());
    }

    public function test_a_movement_on_the_start_date_belongs_to_the_start_value()
    {
        $change = $this->week([
            ['Bought at start', [self::buy('2026-09-30', '100', 100000)], ['2026-09-30' => '10.0000', '2026-10-07' => '10.5000']],
        ]);

        $this->assertSame(100000, $change->startPaise());
        $this->assertSame(0, $change->netFlowPaise());
        $this->assertSame(5000, $change->marketPaise());
    }

    public function test_a_start_on_a_weekend_or_holiday_uses_the_previous_nav()
    {
        // Week to Sun 11 Oct starts Sun 4 Oct; Fri 2 Oct was a holiday, so Thu 1 Oct's NAV applies.
        $change = (new PortfolioChange)->over(ChangePeriod::Week, CarbonImmutable::parse('2026-10-11'), [
            ['Fund', new HoldingInput([self::buy('2026-09-01', '100', 100000)], null, null), [
                '2026-10-01' => '10.0000', '2026-10-05' => '20.0000', '2026-10-09' => '30.0000',
            ]],
        ]);

        $this->assertSame('2026-10-04', $change->from);
        $this->assertSame(100000, $change->startPaise());
        $this->assertSame(300000, $change->endPaise());
    }

    public function test_month_to_date_starts_at_the_previous_month_end()
    {
        $this->assertSame('2026-09-30', ChangePeriod::MonthToDate->startFor(CarbonImmutable::parse('2026-10-07'))->toDateString());
        $this->assertSame('2026-09-07', ChangePeriod::Month->startFor(CarbonImmutable::parse('2026-10-07'))->toDateString());
    }

    public function test_best_and_worst_rank_funds_held_at_the_start()
    {
        $change = $this->week([
            ['Up', [self::buy('2026-09-01', '100', 100000)], ['2026-09-30' => '10.0000', '2026-10-07' => '11.0000']],
            ['Down', [self::buy('2026-09-01', '100', 100000)], ['2026-09-30' => '10.0000', '2026-10-07' => '9.5000']],
            ['New', [self::buy('2026-10-06', '100', 100000)], ['2026-10-06' => '10.0000', '2026-10-07' => '15.0000']],
            ['Sold long ago', [self::buy('2026-01-01', '100', 100000), self::sell('2026-02-01', '100', 100000)], ['2026-09-30' => '10.0000']],
        ]);

        $this->assertSame('Up', $change->best()?->name);
        $this->assertSame('Down', $change->worst()?->name);
        $this->assertCount(3, $change->funds, 'A fund with nothing in the period is left out.');
        $this->assertSame('New', $change->funds[0]->name, 'Largest end value first.');
    }

    public function test_the_array_form_includes_totals_and_the_period()
    {
        $array = $this->week([
            ['Steady', [self::buy('2026-09-01', '100', 100000)], ['2026-09-30' => '10.0000', '2026-10-07' => '11.0000']],
        ], sipInstallments: 2)->toArray();

        $this->assertSame('7d', $array['period']);
        $this->assertSame('last 7 days', $array['label']);
        $this->assertSame(10000, $array['market_paise']);
        $this->assertSame(10.0, $array['market_pct']);
        $this->assertSame(2, $array['sip_installments']);
        $this->assertSame('Steady', $array['best']['name']);
        $this->assertNull($array['worst']);
    }

    /**
     * @param  list<array{string, list<Movement>, array<string, string>}>  $funds
     */
    private function week(array $funds, int $sipInstallments = 0): PortfolioChangeResult
    {
        return (new PortfolioChange)->over(
            ChangePeriod::Week,
            CarbonImmutable::parse('2026-10-07'),
            array_map(fn (array $fund) => [$fund[0], new HoldingInput($fund[1], null, null), $fund[2]], $funds),
            $sipInstallments,
        );
    }

    /**
     * @param  numeric-string  $units
     */
    private static function buy(string $date, string $units, int $paise): Movement
    {
        return new Movement(CarbonImmutable::parse($date), true, $units, $paise);
    }

    /**
     * @param  numeric-string  $units
     */
    private static function sell(string $date, string $units, int $paise): Movement
    {
        return new Movement(CarbonImmutable::parse($date), false, $units, $paise);
    }
}
