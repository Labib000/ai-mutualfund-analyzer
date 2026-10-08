<?php

namespace Tests\Unit\Ai;

use App\Ai\PortfolioContext;
use App\Enums\XirrStatus;
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
        $this->assertStringContainsString('investing since 31 Jan 2026 | running SIP: ₹5,000.00 monthly on day 31', $text);
        $this->assertStringContainsString('2. Parag Parikh Liquid Fund - Direct Plan - Growth | category: Debt Scheme - Liquid Fund | asset class: Debt', $text);
        $this->assertStringContainsString('XIRR +6.74% | ', $text);
        $this->assertStringContainsString('no running SIP', $text);
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

    public function test_xirr_that_is_not_shown_is_explained()
    {
        $recent = PortfolioContext::render([self::fund(['xirr' => null, 'xirr_status' => XirrStatus::TooRecent])], self::total(), []);

        $this->assertStringContainsString('XIRR not available (investments span less than 30 days)', $recent);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{name: string, category: string, asset_class: string, invested_paise: int, value_paise: int, unrealised_gain_paise: int, absolute_return_pct: ?float, realised_gain_paise: int, xirr: ?float, xirr_status: XirrStatus, first_investment: ?string, sips: list<array{amount_paise: int, day: int}>}
     */
    private static function fund(array $overrides = []): array
    {
        /** @var array{name: string, category: string, asset_class: string, invested_paise: int, value_paise: int, unrealised_gain_paise: int, absolute_return_pct: ?float, realised_gain_paise: int, xirr: ?float, xirr_status: XirrStatus, first_investment: ?string, sips: list<array{amount_paise: int, day: int}>} */
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
