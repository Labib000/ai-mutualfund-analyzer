<?php

namespace Tests\Feature;

use App\Models\Holding;
use App\Models\Scheme;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsPortfolio;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsPortfolio, RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_new_user_sees_an_empty_dashboard()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('fund_count', 0)
                ->where('history', [])
                ->where('allocation', ['classes' => [], 'categories' => []])
                ->where('top_holdings', [])
                ->where('insights', [])
                ->where('changes', [])
                ->where('summary.invested_paise', 0));
    }

    public function test_the_dashboard_matches_the_fund_figures()
    {
        $this->buildPortfolio();
        $this->actingAs($this->user);
        $this->buildPerformanceScenario();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('fund_count', 1)
                // The same figures PerformanceTest checks against LibreOffice.
                ->where('summary.invested_paise', 8380194)
                ->where('summary.value_paise', 9108390)
                ->where('summary.xirr_pct', 15.63)
                ->where('history.0.date', '2025-10-05')
                ->where('history.0.invested_paise', 500000)
                ->where('history', fn ($history) => collect($history)->last() === [
                    'date' => '2026-10-07', 'value_paise' => 9108390, 'invested_paise' => 8380194,
                ])
                ->where('allocation.classes.0.key', 'equity')
                ->where('allocation.classes.0.pct', 100)
                ->where('allocation.categories.0.label', 'Flexi Cap Fund')
                ->has('top_holdings', 1)
                ->where('top_holdings.0.id', $this->holding->id)
                ->where('changes.7d.to', '2026-10-07')
                ->where('changes.7d.from', '2026-09-30')
                ->where('changes.month.from', '2026-09-30')
                ->where('changes.30d.from', '2026-09-07')
                ->where('changes.7d.end_paise', 9108390)
                ->where('changes', fn ($changes) => collect($changes)->every(
                    fn (array $change) => $change['market_paise'] === $change['end_paise'] - $change['start_paise'] - $change['net_flow_paise'],
                ))
                ->where('insights', fn ($insights) => collect($insights)->contains(fn (array $insight) => $insight === [
                    'code' => 'single_asset_class',
                    'tone' => 'info',
                    'title' => 'All your money is in Equity funds',
                    'detail' => 'Equity funds follow the stock market, so their value can rise and fall sharply over short periods.',
                ])));
    }

    public function test_allocation_and_top_holdings_span_asset_classes()
    {
        $this->buildPortfolio();
        $this->actingAs($this->user);
        $this->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-09-01', 'amount' => '30000']);

        $liquid = Scheme::factory()->create([
            'amfi_code' => 119800,
            'category' => 'Debt Scheme - Liquid Fund',
            'latest_nav' => '10.0000',
            'latest_nav_date' => '2026-10-07',
        ]);
        $this->navProvider->navs[119800] = self::businessDayNavs('2026-09-01', '2026-10-07');
        $this->navProvider->navs[119800]['2026-10-07'] = '10.0000';
        $debt = Holding::factory()->for($this->user)->for($liquid)->create();
        $this->post(route('holdings.purchases.store', $debt), ['txn_date' => '2026-10-07', 'amount' => '10000', 'units' => '1000']);

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('fund_count', 2)
                ->where('allocation.classes.0.key', 'equity')
                ->where('allocation.classes.1.key', 'debt')
                ->where('allocation.classes.1.value_paise', 1000000)
                ->where('allocation.classes', fn ($classes) => abs(collect($classes)->sum('pct') - 100) < 0.02)
                ->where('top_holdings.0.id', $this->holding->id)
                ->where('top_holdings.1.id', $debt->id));
    }

    public function test_other_users_holdings_never_appear()
    {
        $this->buildPortfolio();
        $this->actingAs($this->user);
        $this->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '10000']);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('fund_count', 0)
                ->where('summary.invested_paise', 0)
                ->where('history', []));

        $this->assertSame(1, Transaction::count());
    }

    public function test_the_cached_history_updates_after_new_transactions_and_navs()
    {
        $this->buildPortfolio();
        $this->actingAs($this->user);
        $this->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-09-01', 'amount' => '10000']);

        $lastPoint = fn () => collect($this->get(route('dashboard'))->viewData('page')['props']['history'])->last();

        $before = $lastPoint();

        // A new transaction must show up in the history.
        $this->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '5000']);
        $afterPurchase = $lastPoint();
        $this->assertSame($before['invested_paise'] + 500000, $afterPurchase['invested_paise']);

        // So must a deleted one, even though the count goes back to where it was.
        $this->delete(route('holdings.transactions.destroy', [$this->holding, Transaction::latest('id')->first()]));
        $this->assertSame($before, $lastPoint());

        // And a newer NAV from the nightly import.
        $this->navProvider->navs[self::AMFI_CODE]['2026-10-08'] = '40.0000';
        $this->scheme->update(['latest_nav' => '40.0000', 'latest_nav_date' => '2026-10-08']);
        $afterNav = $lastPoint();
        $this->assertSame('2026-10-08', $afterNav['date']);
        $this->assertGreaterThan($before['value_paise'], $afterNav['value_paise']);
    }

    /**
     * The PerformanceTest scenario: a year of ₹5,000 SIP installments, a ₹20,000 lump sum, 100 units redeemed.
     */
    private function buildPerformanceScenario(): void
    {
        $this->post(route('holdings.sips.store', $this->holding), ['amount' => '5000', 'day_of_month' => 5, 'start_date' => '2025-10-01']);
        $this->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2025-12-15', 'amount' => '20000']);
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-06-10', 'units' => '100']);
    }
}
