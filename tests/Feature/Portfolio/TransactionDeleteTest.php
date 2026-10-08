<?php

namespace Tests\Feature\Portfolio;

use App\Models\Holding;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPortfolio;
use Tests\TestCase;

class TransactionDeleteTest extends TestCase
{
    use BuildsPortfolio, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPortfolio();
        $this->actingAs($this->user);
    }

    public function test_a_purchase_can_be_deleted()
    {
        $this->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '10000']);

        $this->delete(route('holdings.transactions.destroy', [$this->holding, Transaction::sole()]))
            ->assertRedirect(route('holdings.show', $this->holding));

        $this->assertSame(0, Transaction::count());
    }

    public function test_deleting_a_purchase_that_funds_a_later_redemption_is_blocked()
    {
        $this->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '10000']);
        $purchase = Transaction::sole();
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-07', 'all_units' => true]);

        $this->delete(route('holdings.transactions.destroy', [$this->holding, $purchase]))
            ->assertSessionHasErrors(['transaction' => 'Deleting this would leave the redemption on 7 Oct 2026 with more units than you held.']);

        $this->assertSame(2, Transaction::count());
    }

    public function test_a_redemption_can_always_be_deleted()
    {
        $this->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '10000']);
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-07', 'all_units' => true]);
        $redemption = Transaction::latest('id')->first();

        $this->delete(route('holdings.transactions.destroy', [$this->holding, $redemption]))->assertSessionHasNoErrors();

        $this->assertSame(1, Transaction::count());
    }

    public function test_a_transaction_from_another_holding_is_not_found()
    {
        $otherHolding = Holding::factory()->for($this->user)->create();
        $foreign = Transaction::factory()->for($otherHolding)->create();

        $this->delete(route('holdings.transactions.destroy', [$this->holding, $foreign]))->assertNotFound();

        $this->assertSame(1, Transaction::count());
    }

    public function test_another_users_transaction_is_not_found()
    {
        $foreignHolding = Holding::factory()->for(User::factory())->create();
        $foreign = Transaction::factory()->for($foreignHolding)->create();

        $this->delete(route('holdings.transactions.destroy', [$foreignHolding, $foreign]))->assertNotFound();
    }
}
