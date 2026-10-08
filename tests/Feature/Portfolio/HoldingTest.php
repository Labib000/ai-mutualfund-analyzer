<?php

namespace Tests\Feature\Portfolio;

use App\Models\Holding;
use App\Models\Scheme;
use App\Models\Sip;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsPortfolio;
use Tests\TestCase;

class HoldingTest extends TestCase
{
    use BuildsPortfolio, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPortfolio();
    }

    public function test_guests_are_redirected_to_login()
    {
        auth()->logout();

        $this->get(route('holdings.index'))->assertRedirect(route('login'));
    }

    public function test_the_portfolio_lists_holdings_with_units_and_value()
    {
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '10000']);
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-07', 'units' => '43.507']);

        $this->get(route('holdings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('holdings/index')
                ->has('holdings', 1)
                ->where('holdings.0.id', $this->holding->id)
                ->where('holdings.0.performance.units_held', '300.000')
                ->where('holdings.0.performance.value_paise', (int) bcmul($this->navOn('2026-10-07'), '30000', 0))
                // FIFO: the 43.507 units sold cost ₹10,000 × 43.507 / 343.507 = ₹1,266.55.
                ->where('holdings.0.performance.invested_paise', 873345)
                ->where('holdings.0.performance.xirr_status', 'too_recent')
                ->where('holdings.0.scheme.name', "Axis Children's Fund - Direct Plan - Growth Option")
                ->where('summary.invested_paise', 873345)
                ->where('summary.units_held', null));
    }

    public function test_the_portfolio_only_shows_the_users_own_holdings()
    {
        Holding::factory()->for(User::factory())->create();

        $this->actingAs($this->user)
            ->get(route('holdings.index'))
            ->assertInertia(fn (Assert $page) => $page->has('holdings', 1));
    }

    public function test_search_matches_every_word_in_any_order()
    {
        Scheme::factory()->create(['name' => 'Parag Parikh Flexi Cap Fund - Direct Plan - Growth']);
        Scheme::factory()->create(['name' => 'Parag Parikh Flexi Cap Fund - Regular Plan - Growth']);
        Scheme::factory()->create(['name' => 'HDFC Flexi Cap Fund - Direct Plan - Growth']);

        $this->actingAs($this->user)
            ->get(route('holdings.create', ['q' => 'direct flexi parag']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('holdings/create')
                ->where('query', 'direct flexi parag')
                ->has('results', 1)
                ->where('results.0.name', 'Parag Parikh Flexi Cap Fund - Direct Plan - Growth')
                ->where('results.0.is_held', false));
    }

    public function test_search_excludes_inactive_schemes_and_those_without_a_nav()
    {
        Scheme::factory()->create(['name' => 'Closed Gilt Fund', 'is_active' => false]);
        Scheme::factory()->create(['name' => 'Segregated Gilt Fund', 'latest_nav' => null]);
        Scheme::factory()->create(['name' => 'Open Gilt Fund']);

        $this->actingAs($this->user)
            ->get(route('holdings.create', ['q' => 'gilt']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('results', 1)
                ->where('results.0.name', 'Open Gilt Fund'));
    }

    public function test_search_marks_schemes_already_held()
    {
        $this->actingAs($this->user)
            ->get(route('holdings.create', ['q' => 'axis children']))
            ->assertInertia(fn (Assert $page) => $page->where('results.0.is_held', true));
    }

    public function test_search_treats_like_wildcards_literally()
    {
        Scheme::factory()->create(['name' => 'Some Fund']);

        $this->actingAs($this->user)
            ->get(route('holdings.create', ['q' => '%%']))
            ->assertInertia(fn (Assert $page) => $page->has('results', 0));
    }

    public function test_short_queries_return_no_results()
    {
        $this->actingAs($this->user)
            ->get(route('holdings.create', ['q' => 'a']))
            ->assertInertia(fn (Assert $page) => $page->has('results', 0));
    }

    public function test_a_scheme_can_be_added_once()
    {
        $scheme = Scheme::factory()->create();

        $response = $this->actingAs($this->user)->post(route('holdings.store'), ['scheme_id' => $scheme->id]);

        $holding = Holding::where('scheme_id', $scheme->id)->sole();
        $response->assertRedirect(route('holdings.show', $holding));
        $this->assertSame($this->user->id, $holding->user_id);

        $this->post(route('holdings.store'), ['scheme_id' => $scheme->id])
            ->assertSessionHasErrors(['scheme_id' => 'This fund is already in your portfolio.']);
    }

    public function test_inactive_schemes_cannot_be_added()
    {
        $scheme = Scheme::factory()->create(['is_active' => false]);

        $this->actingAs($this->user)
            ->post(route('holdings.store'), ['scheme_id' => $scheme->id])
            ->assertSessionHasErrors('scheme_id');
    }

    public function test_the_holding_page_shows_transactions_and_sips()
    {
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-04', 'amount' => '10000']);
        $this->post(route('holdings.sips.store', $this->holding), ['amount' => '5000', 'day_of_month' => 31, 'start_date' => '2026-09-01']);

        $this->get(route('holdings.show', $this->holding))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('holdings/show')
                ->where('today', '2026-10-08')
                ->has('transactions', 2)
                ->where('transactions.0.type', 'purchase')
                ->where('transactions.0.txn_date', '2026-10-04')
                ->where('transactions.0.nav_date', '2026-10-05')
                ->where('transactions.0.units', '343.507')
                ->where('transactions.1.type', 'sip_installment')
                ->has('sips', 1)
                ->where('sips.0.is_running', true)
                ->where('sips.0.next_due', '2026-10-31')
                ->where('sips.0.earliest_end_date', '2026-09-30'));
    }

    public function test_another_users_holding_is_not_found()
    {
        $other = Holding::factory()->for(User::factory())->create();

        $this->actingAs($this->user)->get(route('holdings.show', $other))->assertNotFound();
        $this->actingAs($this->user)->delete(route('holdings.destroy', $other))->assertNotFound();

        $this->assertModelExists($other);
    }

    public function test_removing_a_holding_deletes_its_transactions_and_sips()
    {
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '10000']);
        $this->post(route('holdings.sips.store', $this->holding), ['amount' => '5000', 'day_of_month' => 5, 'start_date' => '2026-09-01']);

        $this->delete(route('holdings.destroy', $this->holding))->assertRedirect(route('holdings.index'));

        $this->assertModelMissing($this->holding);
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, Sip::count());
        $this->assertModelExists($this->scheme);
    }
}
