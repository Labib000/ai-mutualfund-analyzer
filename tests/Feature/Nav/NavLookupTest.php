<?php

namespace Tests\Feature\Nav;

use App\Models\NavHistory;
use App\Models\Scheme;
use App\Nav\NavLookup;
use App\Nav\NavProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeNavProvider;
use Tests\TestCase;

/**
 * October 2026: Thu 1st trading, Fri 2nd holiday (Gandhi Jayanti), Sat 3rd and Sun 4th weekend, Mon 5th trading.
 */
class NavLookupTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 135762;

    private FakeNavProvider $provider;

    private NavLookup $lookup;

    private Scheme $scheme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new FakeNavProvider([self::CODE => [
            '2026-09-30' => '29.1843',
            '2026-10-01' => '29.0001',
            '2026-10-05' => '29.1100',
            '2026-10-06' => '29.3369',
            '2026-10-07' => '29.2304',
        ]]);
        $this->lookup = new NavLookup($this->provider);

        $this->scheme = Scheme::factory()->create([
            'amfi_code' => self::CODE,
            'latest_nav' => '29.2304',
            'latest_nav_date' => '2026-10-07',
        ]);
    }

    public function test_a_purchase_on_a_trading_day_uses_that_days_nav()
    {
        $point = $this->lookup->forPurchase($this->scheme, CarbonImmutable::parse('2026-10-06'));

        $this->assertSame('2026-10-06', $point?->date->toDateString());
        $this->assertSame('29.3369', $point->nav);
    }

    public function test_a_purchase_on_a_holiday_uses_the_next_business_days_nav()
    {
        $point = $this->lookup->forPurchase($this->scheme, CarbonImmutable::parse('2026-10-02'));

        $this->assertSame('2026-10-05', $point?->date->toDateString());
        $this->assertSame('29.1100', $point->nav);
    }

    public function test_a_purchase_on_a_weekend_uses_the_next_business_days_nav()
    {
        $point = $this->lookup->forPurchase($this->scheme, CarbonImmutable::parse('2026-10-04'));

        $this->assertSame('2026-10-05', $point?->date->toDateString());
    }

    public function test_a_purchase_whose_nav_is_not_published_yet_returns_null()
    {
        $this->assertNull($this->lookup->forPurchase($this->scheme, CarbonImmutable::parse('2026-10-08')));
    }

    public function test_a_purchase_nav_more_than_seven_days_later_is_not_used()
    {
        $provider = new FakeNavProvider([self::CODE => ['2026-09-01' => '10.0000', '2026-09-20' => '11.0000']]);

        $this->assertNull((new NavLookup($provider))->forPurchase($this->scheme, CarbonImmutable::parse('2026-09-10')));
    }

    public function test_valuation_on_a_weekend_uses_the_previous_nav()
    {
        $point = $this->lookup->forValuation($this->scheme, CarbonImmutable::parse('2026-10-04'));

        $this->assertSame('2026-10-01', $point?->date->toDateString());
        $this->assertSame('29.0001', $point->nav);
    }

    public function test_valuation_before_the_first_nav_returns_null()
    {
        $this->assertNull($this->lookup->forValuation($this->scheme, CarbonImmutable::parse('2026-09-01')));
    }

    public function test_the_first_lookup_fetches_full_history_and_tracks_the_scheme()
    {
        $this->lookup->forValuation($this->scheme, CarbonImmutable::parse('2026-10-07'));

        $this->assertSame([[self::CODE, null]], $this->provider->calls);
        $this->assertSame(5, NavHistory::count());
        $this->assertNotNull($this->scheme->fresh()?->history_synced_at);
    }

    public function test_later_lookups_are_served_from_the_cache()
    {
        $this->lookup->forValuation($this->scheme, CarbonImmutable::parse('2026-10-07'));
        $this->lookup->forPurchase($this->scheme, CarbonImmutable::parse('2026-10-02'));
        $this->lookup->forValuation($this->scheme, CarbonImmutable::parse('2026-10-01'));

        $this->assertCount(1, $this->provider->calls);
    }

    public function test_a_gap_behind_the_latest_nav_fetches_only_the_missing_range()
    {
        $this->lookup->forValuation($this->scheme, CarbonImmutable::parse('2026-10-07'));

        // The nightly import was missed on the 8th and saw the 9th.
        $this->provider->navs[self::CODE]['2026-10-08'] = '29.4000';
        $this->provider->navs[self::CODE]['2026-10-09'] = '29.5000';
        $this->scheme->update(['latest_nav' => '29.5000', 'latest_nav_date' => '2026-10-09']);

        $point = $this->lookup->forPurchase($this->scheme, CarbonImmutable::parse('2026-10-08'));

        $this->assertSame('29.4000', $point?->nav);
        $this->assertSame([self::CODE, '2026-10-08'], $this->provider->calls[1]);
        $this->assertSame(7, NavHistory::count());
    }

    public function test_a_failed_first_fetch_throws_and_leaves_the_scheme_untracked()
    {
        $this->provider->failing = true;

        try {
            $this->lookup->forValuation($this->scheme, CarbonImmutable::parse('2026-10-07'));
            $this->fail('Expected a NavProviderException.');
        } catch (NavProviderException) {
            $this->assertNull($this->scheme->fresh()?->history_synced_at);
        }
    }

    public function test_a_failed_gap_refresh_falls_back_to_cached_history()
    {
        $this->lookup->forValuation($this->scheme, CarbonImmutable::parse('2026-10-07'));
        $this->scheme->update(['latest_nav_date' => '2026-10-09']);
        $this->provider->failing = true;

        $point = $this->lookup->forValuation($this->scheme, CarbonImmutable::parse('2026-10-09'));

        $this->assertSame('2026-10-07', $point?->date->toDateString());
    }
}
