<?php

namespace Tests\Unit\Ai;

use App\Ai\PortfolioContext;
use App\Enums\ChangePeriod;
use App\Enums\XirrStatus;
use App\Portfolio\ChangeFund;
use App\Portfolio\PortfolioChangeResult;
use PHPUnit\Framework\TestCase;

class PortfolioContextTest extends TestCase
{
    public function test_it_renders_totals_allocation_and_each_fund()
    {
        $text = PortfolioContext::render(
            [
                self::fund([
                    'name' => 'Axis Children\'s Fund - Direct Plan - Growth Option',
                    'invested_paise' => 4500000,
                    'value_paise' => 4488392,
                    'unrealised_gain_paise' => -11608,
                    'absolute_return_pct' => -0.258,
                    'xirr' => -0.0073,
                    'xirr_status' => XirrStatus::ShortPeriod,
                    'first_investment' => '2026-01-31',
                    'fund_stats' => 'fund NAV return 1y +21.7%; volatility 14.2%',
                    'sips' => [['amount_paise' => 500000, 'day' => 31]],
                ]),
                self::fund([
                    'name' => 'Parag Parikh Liquid Fund - Direct Plan - Growth',
                    'category' => 'Debt Scheme - Liquid Fund',
                    'asset_class' => 'Debt',
                    'xirr' => 0.0674,
                    'xirr_status' => XirrStatus::Ok,
                ]),
            ],
            [
                'invested_paise' => 6500000,
                'value_paise' => 6568204,
                'unrealised_gain_paise' => 68204,
                'absolute_return_pct' => 1.049,
                'realised_gain_paise' => 0,
                'xirr' => 0.0246,
                'xirr_status' => XirrStatus::ShortPeriod,
                'valued_on' => '2026-10-07',
            ],
            [['label' => 'Solution oriented', 'pct' => 68.34], ['label' => 'Debt', 'pct' => 31.66]],
        );

        $this->assertStringContainsString('Values in Indian rupees, at NAVs up to 7 Oct 2026.', $text);
        $this->assertStringContainsString('TOTAL: invested ₹65,000.00; current value ₹65,682.04; unrealised gain +₹682.04 (+1.05%)', $text);
        $this->assertStringContainsString('XIRR +2.46% (annualised from less than a year, so not yet reliable)', $text);
        $this->assertStringContainsString('ALLOCATION BY ASSET CLASS: Solution oriented 68.3%, Debt 31.7%.', $text);
        $this->assertStringContainsString('FUNDS (2):', $text);
        $this->assertStringContainsString("1. Axis Children's Fund - Direct Plan - Growth Option | category: Equity Scheme - Flexi Cap Fund", $text);
        $this->assertStringContainsString('unrealised gain −₹116.08 (−0.26%)', $text);
        $this->assertStringContainsString('XIRR −0.73% (annualised from less than a year, so not yet reliable) | fund NAV return 1y +21.7%; volatility 14.2% | investing since', $text);
        $this->assertStringContainsString('investing since 31 Jan 2026 | running SIP: ₹5,000.00 monthly on day 31', $text);
        $this->assertStringContainsString('2. Parag Parikh Liquid Fund - Direct Plan - Growth | category: Debt Scheme - Liquid Fund | invested', $text);
        $this->assertStringContainsString('XIRR +6.74% | ', $text);
        $this->assertStringContainsString('no running SIP', $text);
    }

    public function test_the_asset_class_is_named_only_when_the_category_doesnt_say_it()
    {
        $legacy = PortfolioContext::render([self::fund(['category' => 'Growth', 'asset_class' => 'Equity'])], self::total(), []);

        $this->assertStringContainsString('category: Growth | asset class: Equity |', $legacy);
    }

    public function test_a_fully_redeemed_fund_shows_its_realised_gain()
    {
        $text = PortfolioContext::render(
            [self::fund(['invested_paise' => 0, 'value_paise' => 0, 'unrealised_gain_paise' => 0, 'absolute_return_pct' => null, 'realised_gain_paise' => 250000])],
            self::total(),
            [],
        );

        $this->assertStringContainsString('fully redeemed, realised gain +₹2,500.00', $text);
        $this->assertStringNotContainsString('ALLOCATION', $text);
    }

    public function test_observations_are_listed_after_the_funds()
    {
        $text = PortfolioContext::render([self::fund()], self::total(), [], ['Test Fund is a Regular plan. Regular plans include a distributor commission.']);

        $this->assertStringEndsWith("OBSERVATIONS (found by Hisaab's rules):\n- Test Fund is a Regular plan. Regular plans include a distributor commission.", $text);
        $this->assertStringNotContainsString('OBSERVATIONS', PortfolioContext::render([self::fund()], self::total(), []));
    }

    public function test_cash_flow_by_financial_year_is_one_line()
    {
        $text = PortfolioContext::render([self::fund()], self::total(), [], years: [
            ['label' => 'FY 2025-26', 'invested_paise' => 6000000, 'redeemed_paise' => 0],
            ['label' => 'FY 2026-27', 'invested_paise' => 3000000, 'redeemed_paise' => 1050000],
        ]);

        $this->assertStringContainsString(
            'CASH FLOW BY FINANCIAL YEAR (April to March): FY 2025-26 invested ₹60,000.00; FY 2026-27 invested ₹30,000.00, redeemed ₹10,500.00.',
            $text,
        );
    }

    public function test_the_recent_change_shows_totals_and_the_biggest_movers_only()
    {
        $change = new PortfolioChangeResult(ChangePeriod::Month, '2026-09-07', '2026-10-07', [
            new ChangeFund('Up Fund - Direct', 100000, 110000, 0),
            new ChangeFund('Flat Fund - Direct', 100000, 100000, 0),
            new ChangeFund('Down Fund - Direct', 100000, 95000, 0),
        ], 1);

        $text = PortfolioContext::render([self::fund()], self::total(), [], change: $change);

        $this->assertStringContainsString('CHANGE OVER THE LAST 30 DAYS (7 Sep 2026 to 7 Oct 2026): value ₹3,000.00 → ₹3,050.00; new money in (purchases and SIPs minus redemptions) ₹0.00; market movement +₹50.00 (+1.67%); SIP installments recorded: 1.', $text);
        $this->assertStringContainsString('- Moved most: Up Fund - Direct: market movement +₹100.00 (+10.00%)', $text);
        $this->assertStringContainsString('- Moved least: Down Fund - Direct: market movement −₹50.00 (−5.00%)', $text);
        $this->assertStringNotContainsString('Flat Fund', $text);
    }

    public function test_a_large_portfolio_stays_within_the_token_budget()
    {
        $funds = [];
        $changeFunds = [];
        for ($i = 1; $i <= 20; $i++) {
            $funds[] = self::fund([
                'name' => "Aditya Birla Sun Life Equity Hybrid '95 Fund {$i} - Direct Plan - Growth Option",
                'category' => 'Hybrid Scheme - Aggressive Hybrid Fund',
                'asset_class' => 'Hybrid',
                'invested_paise' => 123456789,
                'value_paise' => 234567890,
                'unrealised_gain_paise' => 111111101,
                'absolute_return_pct' => 90.0,
                'realised_gain_paise' => 1234567,
                'first_investment' => '2016-04-05',
                'fund_stats' => 'fund NAV return 1y +21.7%, 3y +15.2% p.a., 5y +18.9% p.a.; volatility 14.2%; largest fall −38.5%',
                'sips' => [['amount_paise' => 1000000, 'day' => 5], ['amount_paise' => 500000, 'day' => 31]],
            ]);
            $changeFunds[] = new ChangeFund("Aditya Birla Sun Life Equity Hybrid '95 Fund {$i} - Direct Plan - Growth Option", 230000000, 234567890, 1500000);
        }

        $years = [];
        for ($year = 2016; $year <= 2026; $year++) {
            $years[] = ['label' => sprintf('FY %d-%02d', $year, ($year + 1) % 100), 'invested_paise' => 18000000, 'redeemed_paise' => 2500000];
        }

        $text = PortfolioContext::render(
            $funds,
            [...self::total(), 'invested_paise' => 2469135780, 'value_paise' => 4691357800, 'valued_on' => '2026-10-07'],
            [['label' => 'Hybrid', 'pct' => 100.0]],
            observations: array_fill(0, 8, str_repeat('An observation about the portfolio with some detail. ', 4)),
            years: $years,
            change: new PortfolioChangeResult(ChangePeriod::Month, '2026-09-07', '2026-10-07', $changeFunds, 40),
        );

        // About 4 characters a token: even this large portfolio stays near 3.2K
        // tokens, leaving room for the system prompt, chat history and answer
        // within Groq's free-tier limit of about 8K tokens a minute.
        $this->assertLessThan(13000, mb_strlen($text));
    }

    public function test_xirr_that_is_not_shown_is_explained()
    {
        $recent = PortfolioContext::render([self::fund(['xirr' => null, 'xirr_status' => XirrStatus::TooRecent])], self::total(), []);

        $this->assertStringContainsString('XIRR not available (investments span less than 30 days)', $recent);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{name: string, category: string, asset_class: string, invested_paise: int, value_paise: int, unrealised_gain_paise: int, absolute_return_pct: ?float, realised_gain_paise: int, xirr: ?float, xirr_status: XirrStatus, first_investment: ?string, fund_stats: ?string, sips: list<array{amount_paise: int, day: int}>}
     */
    private static function fund(array $overrides = []): array
    {
        /** @var array{name: string, category: string, asset_class: string, invested_paise: int, value_paise: int, unrealised_gain_paise: int, absolute_return_pct: ?float, realised_gain_paise: int, xirr: ?float, xirr_status: XirrStatus, first_investment: ?string, fund_stats: ?string, sips: list<array{amount_paise: int, day: int}>} */
        return [...[
            'name' => 'Test Fund',
            'category' => 'Equity Scheme - Flexi Cap Fund',
            'asset_class' => 'Equity',
            'invested_paise' => 100000,
            'value_paise' => 110000,
            'unrealised_gain_paise' => 10000,
            'absolute_return_pct' => 10.0,
            'realised_gain_paise' => 0,
            'xirr' => 0.1,
            'xirr_status' => XirrStatus::Ok,
            'first_investment' => null,
            'fund_stats' => null,
            'sips' => [],
        ], ...$overrides];
    }

    /**
     * @return array{invested_paise: int, value_paise: int, unrealised_gain_paise: int, absolute_return_pct: ?float, realised_gain_paise: int, xirr: ?float, xirr_status: XirrStatus, valued_on: ?string}
     */
    private static function total(): array
    {
        return [
            'invested_paise' => 0, 'value_paise' => 0, 'unrealised_gain_paise' => 0, 'absolute_return_pct' => null,
            'realised_gain_paise' => 250000, 'xirr' => null, 'xirr_status' => XirrStatus::NotMeaningful, 'valued_on' => null,
        ];
    }
}
