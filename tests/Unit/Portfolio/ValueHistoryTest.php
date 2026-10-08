<?php

namespace Tests\Unit\Portfolio;

use App\Portfolio\HoldingInput;
use App\Portfolio\Movement;
use App\Portfolio\ValueHistory;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ValueHistoryTest extends TestCase
{
    public function test_weekly_dates_end_on_the_valuation_date_and_start_at_the_first_transaction()
    {
        $series = $this->series([self::holding([self::buy('2026-01-01', '100.000', 100000)], '2026-01-30', ['2026-01-01' => '10.0000'])]);

        $this->assertSame(
            ['2026-01-01', '2026-01-02', '2026-01-09', '2026-01-16', '2026-01-23', '2026-01-30'],
            array_column($series, 'date'),
        );
    }

    public function test_value_and_fifo_invested_follow_purchases_and_redemptions()
    {
        $series = $this->series([self::holding([
            self::buy('2026-01-01', '100.000', 100000),   // ₹1,000 at ₹10
            self::buy('2026-01-15', '100.000', 200000),   // ₹2,000 at ₹20
            self::sell('2026-01-22', '100.000', 250000),  // FIFO sells the ₹1,000 lot
        ], '2026-01-29', [
            '2026-01-01' => '10.0000',
            '2026-01-15' => '20.0000',
            '2026-01-22' => '25.0000',
            '2026-01-29' => '30.0000',
        ])]);

        $this->assertSame([
            ['date' => '2026-01-01', 'value_paise' => 100000, 'invested_paise' => 100000],
            ['date' => '2026-01-08', 'value_paise' => 100000, 'invested_paise' => 100000],
            ['date' => '2026-01-15', 'value_paise' => 400000, 'invested_paise' => 300000],
            ['date' => '2026-01-22', 'value_paise' => 250000, 'invested_paise' => 200000],
            ['date' => '2026-01-29', 'value_paise' => 300000, 'invested_paise' => 200000],
        ], $series);
    }

    public function test_the_nav_carries_forward_over_weekends_and_gaps()
    {
        // Valued on Saturday 31 Jan; the last NAV is Friday's.
        $series = $this->series([self::holding(
            [self::buy('2026-01-24', '10.000', 10000)],
            '2026-01-31',
            ['2026-01-23' => '9.0000', '2026-01-30' => '12.0000'],
        )]);

        $this->assertSame(['date' => '2026-01-24', 'value_paise' => 9000, 'invested_paise' => 10000], $series[0]);
        $this->assertSame(['date' => '2026-01-31', 'value_paise' => 12000, 'invested_paise' => 10000], $series[1]);
    }

    public function test_funds_are_summed_using_each_funds_own_latest_nav()
    {
        $series = $this->series([
            self::holding([self::buy('2026-01-01', '10.000', 10000)], '2026-01-29', ['2026-01-01' => '10.0000', '2026-01-29' => '11.0000']),
            self::holding([self::buy('2026-01-01', '10.000', 10000)], '2026-01-28', ['2026-01-01' => '10.0000', '2026-01-28' => '12.0000']),
        ]);

        $this->assertSame(['date' => '2026-01-29', 'value_paise' => 11000 + 12000, 'invested_paise' => 20000], end($series));
    }

    public function test_a_fund_bought_later_adds_nothing_before_its_first_purchase()
    {
        $series = $this->series([
            self::holding([self::buy('2026-01-01', '10.000', 10000)], '2026-01-29', ['2026-01-01' => '10.0000']),
            self::holding([self::buy('2026-01-20', '10.000', 50000)], '2026-01-29', ['2026-01-01' => '50.0000']),
        ]);

        $this->assertSame(10000, $series[1]['invested_paise']); // 8 Jan
        $this->assertSame(60000, $series[4]['invested_paise']); // 29 Jan
    }

    public function test_a_portfolio_without_transactions_has_no_history()
    {
        $this->assertSame([], $this->series([self::holding([], '2026-01-29', ['2026-01-29' => '10.0000'])]));
        $this->assertSame([], $this->series([]));
    }

    /**
     * @param  list<array{HoldingInput, array<string, string>}>  $holdings
     * @return list<array{date: string, value_paise: int, invested_paise: int}>
     */
    private function series(array $holdings): array
    {
        return (new ValueHistory)->series($holdings);
    }

    /**
     * @param  list<Movement>  $movements
     * @param  array<string, string>  $navs
     * @return array{HoldingInput, array<string, string>}
     */
    private static function holding(array $movements, string $valuedOn, array $navs): array
    {
        return [new HoldingInput($movements, end($navs) ?: null, CarbonImmutable::parse($valuedOn)), $navs];
    }

    /**
     * @param  numeric-string  $units
     */
    private static function buy(string $date, string $units, int $amountPaise): Movement
    {
        return new Movement(CarbonImmutable::parse($date), true, $units, $amountPaise);
    }

    /**
     * @param  numeric-string  $units
     */
    private static function sell(string $date, string $units, int $amountPaise): Movement
    {
        return new Movement(CarbonImmutable::parse($date), false, $units, $amountPaise);
    }
}
