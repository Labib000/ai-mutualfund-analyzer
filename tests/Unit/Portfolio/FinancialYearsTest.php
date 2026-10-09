<?php

namespace Tests\Unit\Portfolio;

use App\Portfolio\FinancialYears;
use App\Portfolio\Movement;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class FinancialYearsTest extends TestCase
{
    public function test_flows_are_grouped_by_april_to_march_years()
    {
        $years = (new FinancialYears)->of([
            new Movement(CarbonImmutable::parse('2026-04-01'), true, '10', 300000),
            new Movement(CarbonImmutable::parse('2025-03-31'), true, '10', 100000),
            new Movement(CarbonImmutable::parse('2025-04-01'), true, '10', 200000),
            new Movement(CarbonImmutable::parse('2026-03-31'), false, '5', 50000),
            new Movement(CarbonImmutable::parse('2099-12-01'), true, '1', 1),
        ]);

        $this->assertSame([
            ['label' => 'FY 2024-25', 'invested_paise' => 100000, 'redeemed_paise' => 0],
            ['label' => 'FY 2025-26', 'invested_paise' => 200000, 'redeemed_paise' => 50000],
            ['label' => 'FY 2026-27', 'invested_paise' => 300000, 'redeemed_paise' => 0],
            ['label' => 'FY 2099-00', 'invested_paise' => 1, 'redeemed_paise' => 0],
        ], $years);
    }

    public function test_no_movements_give_no_years()
    {
        $this->assertSame([], (new FinancialYears)->of([]));
    }
}
