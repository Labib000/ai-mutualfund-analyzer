<?php

namespace Tests\Feature\Portfolio;

use App\Portfolio\PortfolioPerformance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsPortfolio;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use BuildsPortfolio, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPortfolio();
        $this->actingAs($this->user);

        // A year of ₹5,000 SIP installments, a ₹20,000 lump sum, and 100 units redeemed.
        $this->post(route('holdings.sips.store', $this->holding), ['amount' => '5000', 'day_of_month' => 5, 'start_date' => '2025-10-01']);
        $this->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2025-12-15', 'amount' => '20000']);
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-06-10', 'units' => '100']);
    }

    public function test_the_fund_page_shows_fifo_figures_and_an_xirr_matching_a_spreadsheet()
    {
        $this->get(route('holdings.show', $this->holding))
            ->assertInertia(fn (Assert $page) => $page
                ->where('holding.performance.units_held', '6242.899')
                ->where('holding.performance.invested_paise', 8380194)
                ->where('holding.performance.value_paise', 9108390)
                ->where('holding.performance.unrealised_gain_paise', 728196)
                ->where('holding.performance.realised_gain_paise', 17694)
                ->where('holding.performance.total_gain_paise', 745890)
                ->where('holding.performance.absolute_return_pct', 8.69)
                ->where('holding.performance.xirr_pct', 15.63)
                ->where('holding.performance.xirr_status', 'ok')
                ->where('holding.performance.valued_on', '2026-10-07'));
    }

    public function test_xirr_matches_libreoffice_for_the_same_cash_flows()
    {
        // The 15 transactions above plus the current value on 7 Oct 2026, entered into
        // LibreOffice Calc's XIRR(): 0.156347524112936. The FIFO figures were checked
        // with an independent Python calculation over the same transactions.
        $performance = app(PortfolioPerformance::class)->forHolding($this->holding);

        $this->assertEqualsWithDelta(0.156347524112936, $performance->xirr, 1e-7);
    }

    public function test_the_portfolio_summary_includes_the_fund()
    {
        $this->get(route('holdings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.invested_paise', 8380194)
                ->where('summary.value_paise', 9108390)
                ->where('summary.total_gain_paise', 745890)
                ->where('summary.xirr_pct', 15.63));
    }
}
