<?php

namespace Tests\Feature\Portfolio;

use App\Enums\TransactionType;
use App\Models\Sip;
use App\Models\Transaction;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPortfolio;
use Tests\TestCase;

class SipTest extends TestCase
{
    use BuildsPortfolio, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPortfolio();
        $this->actingAs($this->user);
    }

    public function test_creating_a_sip_backfills_past_installments_with_month_end_and_holiday_rules()
    {
        $this->post(route('holdings.sips.store', $this->holding), [
            'amount' => '5000', 'day_of_month' => 31, 'start_date' => '2026-01-01',
        ])->assertRedirect(route('holdings.show', $this->holding))->assertSessionHasNoErrors();

        $installments = Transaction::orderBy('txn_date')->get();

        $this->assertSame(
            ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30', '2026-07-31', '2026-08-31', '2026-09-30'],
            $installments->map(fn (Transaction $t) => $t->txn_date->toDateString())->all(),
        );

        // 31 Jan and 28 Feb 2026 are Saturdays, so units come from the following Monday's NAV.
        $this->assertSame('2026-02-02', $installments[0]->nav_date->toDateString());
        $this->assertSame('2026-03-02', $installments[1]->nav_date->toDateString());

        $first = $installments[0];
        $this->assertSame(TransactionType::SipInstallment, $first->type);
        $this->assertSame(500000, $first->amount_paise);
        $this->assertSame(25, $first->stamp_duty_paise);
        $this->assertSame(Decimal::round(bcdiv('4999.75', $this->navOn('2026-02-02'), 10), 3), $first->units);

        $this->assertSame('2026-09-30', Sip::sole()->generated_until?->toDateString());
    }

    public function test_a_sip_starting_in_the_future_has_no_installments_yet()
    {
        $this->post(route('holdings.sips.store', $this->holding), [
            'amount' => '5000', 'day_of_month' => 5, 'start_date' => '2026-11-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Transaction::count());
        $this->assertNull(Sip::sole()->generated_until);
    }

    public function test_generation_stops_at_an_installment_whose_nav_is_not_published_and_resumes_later()
    {
        $this->post(route('holdings.sips.store', $this->holding), [
            'amount' => '1000', 'day_of_month' => 8, 'start_date' => '2026-09-01',
        ]);

        // 8 Sep is recorded; today's (8 Oct) NAV isn't published yet.
        $this->assertSame(['2026-09-08'], Transaction::pluck('txn_date')->map->toDateString()->all());

        $this->publishNav('2026-10-08', '29.5000');
        $this->artisan('sips:generate')->assertSuccessful();

        $latest = Transaction::latest('txn_date')->first();
        $this->assertSame('2026-10-08', $latest?->txn_date->toDateString());
        $this->assertSame('29.5000', $latest->nav);
    }

    public function test_generating_twice_creates_nothing_new()
    {
        $this->post(route('holdings.sips.store', $this->holding), [
            'amount' => '1000', 'day_of_month' => 5, 'start_date' => '2026-06-01',
        ]);
        $count = Transaction::count();

        $this->artisan('sips:generate')->assertSuccessful();
        $this->artisan('sips:generate')->assertSuccessful();

        $this->assertSame(5, $count);
        $this->assertSame($count, Transaction::count());
    }

    public function test_editing_a_sip_only_changes_future_installments()
    {
        $this->post(route('holdings.sips.store', $this->holding), [
            'amount' => '5000', 'day_of_month' => 5, 'start_date' => '2026-08-01',
        ]);
        $sip = Sip::sole();

        $this->patch(route('holdings.sips.update', [$this->holding, $sip]), ['amount' => '7000', 'day_of_month' => 20])
            ->assertSessionHasNoErrors();

        $this->travelTo(CarbonImmutable::parse('2026-10-26 10:00'));
        $this->publishNav('2026-10-20', '30.0000');
        $this->artisan('sips:generate')->assertSuccessful();

        $this->assertSame(
            [['2026-08-05', 500000], ['2026-09-05', 500000], ['2026-10-05', 500000], ['2026-10-20', 700000]],
            Transaction::orderBy('txn_date')->get()->map(fn (Transaction $t) => [$t->txn_date->toDateString(), $t->amount_paise])->all(),
        );
    }

    public function test_the_end_date_cannot_be_moved_before_recorded_installments()
    {
        $this->post(route('holdings.sips.store', $this->holding), [
            'amount' => '5000', 'day_of_month' => 5, 'start_date' => '2026-08-01',
        ]);

        $this->patch(route('holdings.sips.update', [$this->holding, Sip::sole()]), [
            'amount' => '5000', 'day_of_month' => 5, 'end_date' => '2026-09-30',
        ])->assertSessionHasErrors(['end_date' => 'The end date cannot be before an installment that has already been recorded.']);
    }

    public function test_a_stopped_sip_creates_no_more_installments()
    {
        $this->post(route('holdings.sips.store', $this->holding), [
            'amount' => '5000', 'day_of_month' => 5, 'start_date' => '2026-08-01',
        ]);
        $sip = Sip::sole();

        $this->post(route('holdings.sips.stop', [$this->holding, $sip]))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-08', $sip->fresh()?->end_date?->toDateString());

        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
        $this->publishNav('2026-11-05', '31.0000');
        $this->artisan('sips:generate')->assertSuccessful();

        $this->assertSame(3, Transaction::count());
    }

    public function test_deleting_a_sip_removes_its_installments_but_not_other_transactions()
    {
        $this->post(route('holdings.purchases.store', $this->holding), ['txn_date' => '2026-10-05', 'amount' => '1000']);
        $this->post(route('holdings.sips.store', $this->holding), [
            'amount' => '5000', 'day_of_month' => 5, 'start_date' => '2026-08-01',
        ]);

        $this->delete(route('holdings.sips.destroy', [$this->holding, Sip::sole()]))->assertSessionHasNoErrors();

        $this->assertSame(0, Sip::count());
        $this->assertSame([TransactionType::Purchase], Transaction::pluck('type')->all());
    }

    public function test_deleting_a_sip_whose_units_were_redeemed_is_blocked()
    {
        $this->post(route('holdings.sips.store', $this->holding), [
            'amount' => '5000', 'day_of_month' => 5, 'start_date' => '2026-08-01',
        ]);
        $this->post(route('holdings.redemptions.store', $this->holding), ['txn_date' => '2026-10-07', 'all_units' => true]);

        $this->delete(route('holdings.sips.destroy', [$this->holding, Sip::sole()]))
            ->assertSessionHasErrors(['sip' => 'Deleting this SIP and its installments would leave the redemption on 7 Oct 2026 with more units than you held.']);

        $this->assertSame(1, Sip::count());
    }

    public function test_a_sip_is_saved_even_when_the_nav_source_is_down_and_backfilled_later()
    {
        $this->navProvider->failing = true;

        $this->post(route('holdings.sips.store', $this->holding), [
            'amount' => '5000', 'day_of_month' => 5, 'start_date' => '2026-08-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Sip::count());
        $this->assertSame(0, Transaction::count());

        $this->navProvider->failing = false;
        $this->artisan('sips:generate')->assertSuccessful();

        $this->assertSame(3, Transaction::count());
    }

    public function test_sip_input_is_validated()
    {
        $this->post(route('holdings.sips.store', $this->holding), [
            'amount' => '-5', 'day_of_month' => 32, 'start_date' => '2026-08-01', 'end_date' => '2026-07-01',
        ])->assertSessionHasErrors(['amount', 'day_of_month', 'end_date']);
    }

    public function test_a_sip_from_another_holding_is_not_found()
    {
        $foreign = Sip::factory()->create();

        $this->post(route('holdings.sips.stop', [$this->holding, $foreign]))->assertNotFound();
    }

    private function publishNav(string $date, string $nav): void
    {
        $this->navProvider->navs[self::AMFI_CODE][$date] = $nav;
        $this->scheme->update(['latest_nav' => $nav, 'latest_nav_date' => $date]);
    }
}
