<?php

namespace Tests\Feature\Portfolio;

use App\Enums\TransactionType;
use App\Models\Holding;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPortfolio;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use BuildsPortfolio, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPortfolio();
    }

    public function test_a_weekend_purchase_uses_the_next_business_days_nav_after_stamp_duty()
    {
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-04', 'amount' => '10000'])
            ->assertRedirect(route('holdings.show', $this->holding))
            ->assertSessionHasNoErrors();

        $transaction = Transaction::sole();
        $this->assertSame(TransactionType::Purchase, $transaction->type);
        $this->assertSame('2026-10-04', $transaction->txn_date->toDateString());
        $this->assertSame('2026-10-05', $transaction->nav_date->toDateString());
        $this->assertSame('29.1100', $transaction->nav);
        $this->assertSame(1000000, $transaction->amount_paise);
        $this->assertSame(50, $transaction->stamp_duty_paise);
        $this->assertSame('343.507', $transaction->units);
        $this->assertFalse($transaction->units_overridden);
    }

    public function test_a_holiday_purchase_uses_the_next_business_days_nav()
    {
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-02', 'amount' => '5000']);

        $this->assertSame('2026-10-05', Transaction::sole()->nav_date->toDateString());
    }

    public function test_units_can_be_overridden_to_match_a_statement()
    {
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '10000', 'units' => '343.5']);

        $transaction = Transaction::sole();
        $this->assertSame('343.500', $transaction->units);
        $this->assertTrue($transaction->units_overridden);
    }

    public function test_amounts_accept_paise_and_indian_grouping()
    {
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '1,00,000.50'])
            ->assertSessionHasNoErrors();

        $this->assertSame(10000050, Transaction::sole()->amount_paise);
    }

    public function test_todays_purchase_is_rejected_until_the_nav_is_published()
    {
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-08', 'amount' => '1000'])
            ->assertSessionHasErrors(['txn_date' => "Today's NAV isn't published yet. Add this after 11 PM IST or tomorrow."]);

        $this->assertSame(0, Transaction::count());
    }

    public function test_future_dates_are_rejected()
    {
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-09', 'amount' => '1000'])
            ->assertSessionHasErrors(['txn_date' => 'The date cannot be in the future.']);
    }

    public function test_dates_before_the_schemes_history_are_rejected()
    {
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2020-01-01', 'amount' => '1000'])
            ->assertSessionHasErrors(['txn_date' => 'No NAV is available for 1 Jan 2020.']);
    }

    public function test_an_unreachable_nav_source_gives_a_friendly_error()
    {
        $this->navProvider->failing = true;

        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '1000'])
            ->assertSessionHasErrors(['txn_date' => "Couldn't fetch NAV history right now, please try again."]);
    }

    public function test_invalid_amounts_and_units_are_rejected()
    {
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '10.005', 'units' => '1.0001'])
            ->assertSessionHasErrors(['amount', 'units']);

        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '0.50'])
            ->assertSessionHasErrors(['amount' => 'The amount must be at least ₹1.']);
    }

    public function test_another_users_holding_is_not_found()
    {
        $other = Holding::factory()->for(User::factory())->for($this->scheme)->create();

        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $other), ['txn_date' => '2026-10-05', 'amount' => '1000'])
            ->assertNotFound();

        $this->assertSame(0, Transaction::count());
    }
}
