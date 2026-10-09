<?php

namespace Tests\Unit\Portfolio;

use App\Enums\SchemePlan;
use App\Enums\XirrStatus;
use App\Portfolio\Insight;
use App\Portfolio\InsightFund;
use App\Portfolio\Insights;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class InsightsTest extends TestCase
{
    private const TODAY = '2026-10-08';

    public function test_a_fund_below_its_cost_is_named_with_its_loss()
    {
        $insight = $this->find('loss', [self::fund(['name' => 'Axis Small Cap Fund - Direct Plan - Growth', 'invested' => 1000000, 'value' => 950000])]);

        $this->assertSame('attention', $insight->tone);
        $this->assertSame('Axis Small Cap Fund is below its cost', $insight->title);
        $this->assertSame('Its current value is ₹500.00 (5.0%) less than the cost of the units held.', $insight->detail);
        $this->assertSame('Why is Axis Small Cap Fund below its cost?', $insight->question);
    }

    public function test_several_losing_funds_are_listed_together()
    {
        $insight = $this->find('loss', [
            self::fund(['name' => 'A Fund - Direct', 'value' => 90000]),
            self::fund(['name' => 'B Fund - Direct', 'value' => 80000]),
            self::fund(['name' => 'C Fund - Direct', 'value' => 120000]),
        ]);

        $this->assertSame('2 funds are below their cost', $insight->title);
        $this->assertStringContainsString('A Fund and B Fund', $insight->detail);
    }

    public function test_a_fund_at_cost_is_not_a_loss()
    {
        $this->assertNotFound('loss', [self::fund(['invested' => 100000, 'value' => 100000])]);
    }

    public function test_regular_plans_are_explained_as_a_fact()
    {
        $insight = $this->find('regular_plan', [self::fund(['name' => 'HDFC Top 100 Fund - Regular Plan - Growth', 'plan' => SchemePlan::Regular])]);

        $this->assertSame('HDFC Top 100 Fund is a Regular plan', $insight->title);
        $this->assertStringContainsString('distributor commission', $insight->detail);
        $this->assertStringNotContainsStringIgnoringCase('switch', $insight->detail);

        $this->assertNotFound('regular_plan', [self::fund(['plan' => SchemePlan::Direct])]);
    }

    public function test_one_fund_above_forty_percent_is_called_out()
    {
        $insight = $this->find('fund_concentration', [
            self::fund(['name' => 'Big Fund - Direct', 'value' => 410000, 'amc' => 'X']),
            self::fund(['value' => 590000 / 2, 'amc' => 'Y']),
            self::fund(['value' => 590000 / 2, 'amc' => 'Z']),
        ]);

        $this->assertSame('Big Fund is 41.0% of your portfolio', $insight->title);
    }

    public function test_exactly_forty_percent_is_not_called_out()
    {
        $this->assertNotFound('fund_concentration', [
            self::fund(['value' => 400000, 'amc' => 'X']),
            self::fund(['value' => 300000, 'amc' => 'Y']),
            self::fund(['value' => 300000, 'amc' => 'Z']),
        ]);
    }

    public function test_a_single_fund_is_not_a_concentration()
    {
        $this->assertNotFound('fund_concentration', [self::fund()]);
        $this->assertNotFound('amc_concentration', [self::fund()]);
    }

    public function test_one_fund_house_above_half_is_called_out()
    {
        $insight = $this->find('amc_concentration', [
            self::fund(['value' => 260000, 'amc' => 'Axis Mutual Fund']),
            self::fund(['value' => 250001, 'amc' => 'Axis Mutual Fund']),
            self::fund(['value' => 489999, 'amc' => 'HDFC Mutual Fund']),
        ]);

        $this->assertSame('51.0% of your money is with Axis Mutual Fund', $insight->title);

        $this->assertNotFound('amc_concentration', [
            self::fund(['value' => 500000, 'amc' => 'Axis Mutual Fund']),
            self::fund(['value' => 500000, 'amc' => 'HDFC Mutual Fund']),
        ]);
    }

    public function test_all_funds_from_one_house_say_so()
    {
        $insight = $this->find('amc_concentration', [self::fund(['amc' => 'Axis Mutual Fund']), self::fund(['amc' => 'Axis Mutual Fund'])]);

        $this->assertSame('All your funds are from Axis Mutual Fund', $insight->title);
    }

    public function test_a_single_asset_class_is_described()
    {
        $insight = $this->find('single_asset_class', [self::fund(), self::fund(['category' => 'Equity Scheme - Small Cap Fund'])]);

        $this->assertSame('All your money is in Equity funds', $insight->title);

        $this->assertNotFound('single_asset_class', [self::fund(), self::fund(['category' => 'Debt Scheme - Liquid Fund'])]);
    }

    public function test_funds_sharing_a_category_are_grouped()
    {
        $insight = $this->find('same_category', [
            self::fund(['name' => 'A Large Cap Fund - Direct', 'category' => 'Equity Scheme - Large Cap Fund']),
            self::fund(['name' => 'B Large Cap Fund - Direct', 'category' => 'Equity Scheme - Large Cap Fund']),
            self::fund(['category' => 'Equity Scheme - Small Cap Fund']),
        ]);

        $this->assertSame('2 funds in Large Cap Fund', $insight->title);
        $this->assertStringStartsWith('A Large Cap Fund and B Large Cap Fund are in the same category', $insight->detail);
    }

    public function test_a_fund_without_a_sip_or_transaction_for_a_year_is_idle()
    {
        $insight = $this->find('idle', [self::fund(['name' => 'Old Fund - Direct', 'last' => '2025-10-07', 'sip' => false])]);

        $this->assertSame('No new money in Old Fund for over a year', $insight->title);

        $this->assertNotFound('idle', [self::fund(['last' => '2025-10-08', 'sip' => false])], 'Exactly a year is not idle yet.');
        $this->assertNotFound('idle', [self::fund(['last' => '2020-01-01', 'sip' => true])], 'A running SIP keeps it active.');
    }

    public function test_a_short_xirr_history_is_mentioned()
    {
        $this->assertSame('Your XIRR covers less than a year', $this->find('short_history', [self::fund()], XirrStatus::ShortPeriod)->title);
        $this->assertSame('XIRR appears after 30 days', $this->find('short_history', [self::fund()], XirrStatus::TooRecent)->title);
        $this->assertNotFound('short_history', [self::fund()]);
    }

    public function test_attention_comes_first_and_redeemed_funds_are_ignored()
    {
        $insights = (new Insights)->of([
            self::fund(['plan' => SchemePlan::Regular, 'invested' => 100000, 'value' => 90000]),
            self::fund(['name' => 'Sold Fund - Direct', 'invested' => 0, 'value' => 0, 'plan' => SchemePlan::Regular]),
        ], XirrStatus::Ok, CarbonImmutable::parse(self::TODAY));

        $this->assertSame('loss', $insights[0]->code);
        $this->assertSame('Test Fund is a Regular plan', $this->insight($insights, 'regular_plan')?->title);
    }

    public function test_an_empty_portfolio_has_no_insights()
    {
        $this->assertSame([], (new Insights)->of([], XirrStatus::NotMeaningful, CarbonImmutable::parse(self::TODAY)));
    }

    /**
     * @param  list<InsightFund>  $funds
     */
    private function find(string $code, array $funds, XirrStatus $xirr = XirrStatus::Ok): Insight
    {
        $insight = $this->insight((new Insights)->of($funds, $xirr, CarbonImmutable::parse(self::TODAY)), $code);
        $this->assertNotNull($insight, "Expected a [{$code}] insight.");

        return $insight;
    }

    /**
     * @param  list<InsightFund>  $funds
     */
    private function assertNotFound(string $code, array $funds, string $message = ''): void
    {
        $this->assertNull($this->insight((new Insights)->of($funds, XirrStatus::Ok, CarbonImmutable::parse(self::TODAY)), $code), $message);
    }

    /**
     * @param  list<Insight>  $insights
     */
    private function insight(array $insights, string $code): ?Insight
    {
        foreach ($insights as $insight) {
            if ($insight->code === $code) {
                return $insight;
            }
        }

        return null;
    }

    /**
     * @param  array{name?: string, amc?: string, category?: string, plan?: ?SchemePlan, invested?: int, value?: int|float, last?: string, sip?: bool}  $o
     */
    private static function fund(array $o = []): InsightFund
    {
        return new InsightFund(
            name: $o['name'] ?? 'Test Fund - Direct Plan - Growth',
            amc: $o['amc'] ?? 'Test Mutual Fund',
            category: $o['category'] ?? 'Equity Scheme - Flexi Cap Fund',
            plan: array_key_exists('plan', $o) ? $o['plan'] : SchemePlan::Direct,
            investedPaise: $o['invested'] ?? 100000,
            valuePaise: (int) ($o['value'] ?? 110000),
            lastTransaction: CarbonImmutable::parse($o['last'] ?? '2026-09-01'),
            hasRunningSip: $o['sip'] ?? false,
        );
    }
}
