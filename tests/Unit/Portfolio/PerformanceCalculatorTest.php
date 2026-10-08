<?php

namespace Tests\Unit\Portfolio;

use App\Enums\XirrStatus;
use App\Portfolio\HoldingInput;
use App\Portfolio\Movement;
use App\Portfolio\Performance;
use App\Portfolio\PerformanceCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PerformanceCalculatorTest extends TestCase
{
    private PerformanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new PerformanceCalculator;
    }

    public function test_a_fund_with_a_partial_redemption()
    {
        // The same cash flows as XirrTest's partial-redemption case (LibreOffice XIRR 0.115020617957131).
        $performance = $this->calculator->forHolding(new HoldingInput([
            self::buy('2025-01-10', '1000.000', 1000000_00),
            self::buy('2025-04-10', '400.000', 500000_00),
            self::sell('2025-09-01', '400.000', 400000_00),
        ], '1250.0000', CarbonImmutable::parse('2026-02-01')));

        $this->assertSame('1000.000', $performance->unitsHeld);
        $this->assertSame(1250000_00, $performance->valuePaise);
        // 600 units left from lot 1 (₹6,00,000) + all of lot 2 (₹5,00,000).
        $this->assertSame(1100000_00, $performance->investedPaise);
        $this->assertSame(150000_00, $performance->unrealisedGainPaise());
        // 400 units of lot 1 cost ₹4,00,000 and were sold for ₹4,00,000.
        $this->assertSame(0, $performance->realisedGainPaise);
        $this->assertEqualsWithDelta(13.6364, $performance->absoluteReturnPct(), 0.0001);
        $this->assertEqualsWithDelta(0.115020617957131, $performance->xirr, 1e-7);
        $this->assertSame(XirrStatus::Ok, $performance->xirrStatus);
        $this->assertSame('2026-02-01', $performance->valuedOn?->toDateString());
    }

    public function test_same_day_purchase_and_redemption_are_ordered_purchase_first()
    {
        $performance = $this->calculator->forHolding(new HoldingInput([
            self::sell('2026-01-10', '10.000', 11000),
            self::buy('2026-01-10', '10.000', 10000),
        ], '11.0000', CarbonImmutable::parse('2026-02-10')));

        $this->assertSame('0.000', $performance->unitsHeld);
        $this->assertSame(1000, $performance->realisedGainPaise);
    }

    public function test_xirr_is_hidden_before_30_days()
    {
        $performance = $this->holdingSpanning(29);

        $this->assertNull($performance->xirr);
        $this->assertSame(XirrStatus::TooRecent, $performance->xirrStatus);
    }

    public function test_xirr_from_30_days_is_marked_as_a_short_period()
    {
        $performance = $this->holdingSpanning(30);

        $this->assertNotNull($performance->xirr);
        $this->assertSame(XirrStatus::ShortPeriod, $performance->xirrStatus);
        $this->assertSame(XirrStatus::ShortPeriod, $this->holdingSpanning(364)->xirrStatus);
    }

    public function test_xirr_from_a_year_is_ok()
    {
        $this->assertSame(XirrStatus::Ok, $this->holdingSpanning(365)->xirrStatus);
    }

    public function test_a_fully_redeemed_fund_keeps_its_realised_gain_and_xirr()
    {
        $performance = $this->calculator->forHolding(new HoldingInput([
            self::buy('2025-01-01', '100.000', 100000_00),
            self::sell('2026-01-01', '100.000', 85000_00),
        ], '900.0000', CarbonImmutable::parse('2026-10-07')));

        $this->assertSame(0, $performance->investedPaise);
        $this->assertSame(0, $performance->valuePaise);
        $this->assertSame(-15000_00, $performance->realisedGainPaise);
        $this->assertNull($performance->absoluteReturnPct());
        $this->assertEqualsWithDelta(-0.15, $performance->xirr, 1e-7);
        $this->assertNull($performance->valuedOn);
    }

    public function test_a_fund_without_transactions()
    {
        $performance = $this->calculator->forHolding(new HoldingInput([], '10.0000', CarbonImmutable::parse('2026-10-07')));

        $this->assertSame(0, $performance->investedPaise);
        $this->assertSame(0, $performance->valuePaise);
        $this->assertSame(XirrStatus::NotMeaningful, $performance->xirrStatus);
    }

    public function test_a_portfolio_adds_up_funds_and_runs_one_xirr_over_all_flows()
    {
        $equity = new HoldingInput([self::buy('2025-01-01', '100.000', 100000_00)], '1100.0000', CarbonImmutable::parse('2026-01-01'));
        $debt = new HoldingInput([self::buy('2025-01-01', '100.000', 100000_00)], '1060.0000', CarbonImmutable::parse('2025-12-31'));

        $portfolio = $this->calculator->forPortfolio([$equity, $debt]);

        $this->assertNull($portfolio->unitsHeld);
        $this->assertSame(200000_00, $portfolio->investedPaise);
        $this->assertSame(216000_00, $portfolio->valuePaise);
        $this->assertSame(16000_00, $portfolio->unrealisedGainPaise());
        $this->assertSame('2026-01-01', $portfolio->valuedOn?->toDateString());
        // Between the two funds' own rates (10% over 365 days, 6% over 364 days).
        $this->assertGreaterThan(0.06, $portfolio->xirr);
        $this->assertLessThan(0.10, $portfolio->xirr);
    }

    public function test_to_array_rounds_percentages()
    {
        $array = $this->calculator->forHolding(new HoldingInput([
            self::buy('2025-01-10', '1000.000', 1000000_00),
            self::buy('2025-04-10', '400.000', 500000_00),
            self::sell('2025-09-01', '400.000', 400000_00),
        ], '1250.0000', CarbonImmutable::parse('2026-02-01')))->toArray();

        $this->assertSame(13.64, $array['absolute_return_pct']);
        $this->assertSame(11.5, $array['xirr_pct']);
        $this->assertSame('ok', $array['xirr_status']);
        $this->assertSame(15000000, $array['total_gain_paise']);
    }

    private function holdingSpanning(int $days): Performance
    {
        $start = CarbonImmutable::parse('2025-01-01');

        return $this->calculator->forHolding(new HoldingInput(
            [self::buy($start->toDateString(), '100.000', 100000)],
            '10.5000',
            $start->addDays($days),
        ));
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
