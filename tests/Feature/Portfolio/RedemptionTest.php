<?php

namespace Tests\Feature\Portfolio;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPortfolio;
use Tests\TestCase;

class RedemptionTest extends TestCase
{
    use BuildsPortfolio, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPortfolio();

        // 343.507 units bought on 5 Oct 2026.
        $this->actingAs($this->user)
            ->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '10000']);
    }

    public function test_redeeming_part_of_a_holding_defaults_the_amount_to_units_times_nav()
    {
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-07', 'units' => '100'])
            ->assertSessionHasNoErrors();

        $redemption = Transaction::where('type', TransactionType::Redemption)->sole();
        $this->assertSame('100.000', $redemption->units);
        $this->assertSame('2026-10-07', $redemption->nav_date->toDateString());
        $this->assertSame((int) bcmul($this->navOn('2026-10-07'), '10000', 0), $redemption->amount_paise);
        $this->assertSame(0, $redemption->stamp_duty_paise);
    }

    public function test_the_amount_received_can_be_entered()
    {
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-07', 'units' => '100', 'amount' => '2900.25']);

        $this->assertSame(290025, Transaction::where('type', TransactionType::Redemption)->sole()->amount_paise);
    }

    public function test_redeeming_all_units()
    {
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-07', 'all_units' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame('343.507', Transaction::where('type', TransactionType::Redemption)->sole()->units);
    }

    public function test_redeeming_more_than_held_is_rejected()
    {
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-07', 'units' => '343.508'])
            ->assertSessionHasErrors(['units' => 'You held only 343.507 units on 7 Oct 2026.']);
    }

    public function test_redeeming_before_the_purchase_is_rejected()
    {
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-01', 'all_units' => true])
            ->assertSessionHasErrors(['units' => 'You held no units on 1 Oct 2026.']);
    }

    public function test_a_back_dated_redemption_that_breaks_a_later_one_is_rejected()
    {
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-07', 'units' => '300']);

        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-06', 'units' => '100'])
            ->assertSessionHasErrors(['units' => 'This would leave the redemption on 7 Oct 2026 with more units than you held.']);
    }

    public function test_units_are_required_unless_redeeming_everything()
    {
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-07'])
            ->assertSessionHasErrors('units');
    }
}
