<?php

namespace Tests\Unit\Portfolio;

use App\Portfolio\Allocation;
use PHPUnit\Framework\TestCase;

class AllocationTest extends TestCase
{
    public function test_shares_by_asset_class_in_fixed_order_and_by_sub_category()
    {
        $allocation = (new Allocation)->of([
            ['category' => 'Debt Scheme - Liquid Fund', 'value_paise' => 2000000],
            ['category' => 'Equity Scheme - Large Cap Fund', 'value_paise' => 5000000],
            ['category' => 'Equity Scheme - Flexi Cap Fund', 'value_paise' => 2000000],
            ['category' => 'Equity Scheme - Large Cap Fund', 'value_paise' => 1000000],
        ]);

        $this->assertSame([
            ['key' => 'equity', 'label' => 'Equity', 'value_paise' => 8000000, 'pct' => 80.0],
            ['key' => 'debt', 'label' => 'Debt', 'value_paise' => 2000000, 'pct' => 20.0],
        ], $allocation['classes']);

        $this->assertSame([
            ['label' => 'Large Cap Fund', 'asset_class' => 'equity', 'value_paise' => 6000000, 'pct' => 60.0],
            ['label' => 'Flexi Cap Fund', 'asset_class' => 'equity', 'value_paise' => 2000000, 'pct' => 20.0],
            ['label' => 'Liquid Fund', 'asset_class' => 'debt', 'value_paise' => 2000000, 'pct' => 20.0],
        ], $allocation['categories']);
    }

    public function test_shares_add_up_to_100()
    {
        $allocation = (new Allocation)->of([
            ['category' => 'Equity Scheme - Large Cap Fund', 'value_paise' => 1],
            ['category' => 'Debt Scheme - Gilt Fund', 'value_paise' => 1],
            ['category' => 'Hybrid Scheme - Arbitrage Fund', 'value_paise' => 1],
        ]);

        $this->assertEqualsWithDelta(100.0, array_sum(array_column($allocation['classes'], 'pct')), 0.02);
    }

    public function test_funds_worth_nothing_are_left_out()
    {
        $allocation = (new Allocation)->of([
            ['category' => 'Equity Scheme - Large Cap Fund', 'value_paise' => 100],
            ['category' => 'Debt Scheme - Gilt Fund', 'value_paise' => 0],
        ]);

        $this->assertSame(['equity'], array_column($allocation['classes'], 'key'));
        $this->assertCount(1, $allocation['categories']);
    }

    public function test_an_empty_portfolio_has_no_allocation()
    {
        $this->assertSame(['classes' => [], 'categories' => []], (new Allocation)->of([]));
    }

    public function test_legacy_categories_without_a_group_keep_their_name()
    {
        $allocation = (new Allocation)->of([['category' => 'Income', 'value_paise' => 100]]);

        $this->assertSame('Income', $allocation['categories'][0]['label']);
        $this->assertSame('debt', $allocation['categories'][0]['asset_class']);
    }
}
