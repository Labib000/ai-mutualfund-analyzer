<?php

namespace Tests\Unit\Portfolio;

use App\Portfolio\CashFlow;
use App\Portfolio\Xirr;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Expected values were computed with LibreOffice Calc's XIRR(), which follows
 * Excel's convention, from the same cash flows (headless conversion of a .fods
 * sheet, October 2026). The first case is the example in Microsoft's XIRR documentation.
 */
class XirrTest extends TestCase
{
    private const TOLERANCE = 1e-7;

    public function test_microsofts_documented_example()
    {
        $this->assertRate(0.373362533518832, [
            [-10000, '2008-01-01'],
            [2750, '2008-03-01'],
            [4250, '2008-10-30'],
            [3250, '2009-02-15'],
            [2750, '2009-04-01'],
        ]);
    }

    public function test_a_two_year_monthly_sip_with_current_value()
    {
        $this->assertRate(0.153193594755617, self::sipFlows());
    }

    public function test_input_order_does_not_matter()
    {
        $flows = self::sipFlows();
        mt_srand(7);
        shuffle($flows);

        $this->assertRate(0.153193594755617, $flows);
    }

    public function test_a_loss_gives_a_negative_rate()
    {
        $this->assertRate(-0.15, [[-100000, '2025-01-01'], [85000, '2026-01-01']]);
    }

    public function test_a_very_high_short_term_return()
    {
        $this->assertRate(24.6758687426491, [[-10000, '2025-01-01'], [50000, '2025-07-01']]);
    }

    public function test_several_flows_on_the_same_day()
    {
        $this->assertRate(0.146141235300258, [
            [-5000, '2025-01-01'],
            [-3000, '2025-01-01'],
            [2000, '2025-06-01'],
            [7000, '2025-12-31'],
        ]);
    }

    public function test_partial_redemptions_before_the_final_value()
    {
        $this->assertRate(0.115020617957131, [
            [-1000000, '2025-01-10'],
            [-500000, '2025-04-10'],
            [400000, '2025-09-01'],
            [1250000, '2026-02-01'],
        ]);
    }

    public function test_a_near_total_loss_is_solved_by_the_bisection_fallback()
    {
        // LibreOffice gives #VALUE! here; with two flows the exact answer is
        // (10 / 10000)^(365 / 366) − 1, since 2024 is a leap year.
        $this->assertRate((10 / 10000) ** (365 / 366) - 1, [[-10000, '2024-01-01'], [10, '2025-01-01']]);
    }

    public function test_zero_amounts_are_ignored()
    {
        $this->assertRate(-0.15, [[-100000, '2025-01-01'], [0, '2025-06-01'], [85000, '2026-01-01']]);
    }

    public function test_flows_without_both_signs_have_no_rate()
    {
        $this->assertNull($this->rate([[-1000, '2025-01-01'], [-2000, '2025-02-01']]));
        $this->assertNull($this->rate([[1000, '2025-01-01'], [2000, '2025-02-01']]));
        $this->assertNull($this->rate([[-1000, '2025-01-01']]));
        $this->assertNull($this->rate([]));
    }

    /**
     * @return list<array{int, string}> ₹5,000 on the 5th of every month for 2024–2025, worth ₹1,40,000 on 10 Jan 2026
     */
    private static function sipFlows(): array
    {
        $flows = [];

        for ($month = 0; $month < 24; $month++) {
            $flows[] = [-5000, CarbonImmutable::create(2024, 1, 5)->addMonths($month)->toDateString()];
        }

        $flows[] = [140000, '2026-01-10'];

        return $flows;
    }

    /**
     * @param  list<array{int, string}>  $flows  Amounts in rupees and Y-m-d dates
     */
    private function assertRate(float $expected, array $flows): void
    {
        $rate = $this->rate($flows);

        $this->assertNotNull($rate);
        $this->assertEqualsWithDelta($expected, $rate, self::TOLERANCE);
    }

    /**
     * @param  list<array{int, string}>  $flows
     */
    private function rate(array $flows): ?float
    {
        return (new Xirr)->rate(array_map(
            fn (array $flow) => new CashFlow(CarbonImmutable::parse($flow[1]), $flow[0] * 100),
            $flows,
        ));
    }
}
