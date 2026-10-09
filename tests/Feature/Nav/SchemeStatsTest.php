<?php

namespace Tests\Feature\Nav;

use App\Models\NavHistory;
use App\Nav\NavLookup;
use App\Nav\SchemeStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPortfolio;
use Tests\TestCase;

class SchemeStatsTest extends TestCase
{
    use BuildsPortfolio, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPortfolio();
    }

    public function test_stats_come_from_the_stored_nav_history()
    {
        app(NavLookup::class)->ensureHistory($this->scheme);

        $stats = app(SchemeStats::class)->for($this->scheme->refresh());

        $this->assertNotNull($stats);
        $this->assertSame('2025-01-01', $stats->since);
        $this->assertSame('2026-10-07', $stats->asOf);

        // NAV on 7 Oct 2025 against 7 Oct 2026, both read from the fixture.
        $expected = (float) bcdiv($this->navOn('2026-10-07'), $this->navOn('2025-10-07'), 12) - 1;
        $this->assertEqualsWithDelta($expected, $stats->return1y, 1e-9);
    }

    public function test_stats_are_cached_until_a_newer_nav_arrives()
    {
        app(NavLookup::class)->ensureHistory($this->scheme);
        $this->scheme->refresh();
        $stats = app(SchemeStats::class);

        $this->assertSame('2026-10-07', $stats->for($this->scheme)?->asOf);

        NavHistory::query()->create(['scheme_id' => $this->scheme->id, 'nav_date' => '2026-10-08', 'nav' => '15.0000']);
        $this->assertSame('2026-10-07', $stats->for($this->scheme)?->asOf, 'Cached until the scheme reports a newer NAV.');

        $this->scheme->update(['latest_nav' => '15.0000', 'latest_nav_date' => '2026-10-08']);
        $this->assertSame('2026-10-08', $stats->for($this->scheme)?->asOf);
    }

    public function test_a_scheme_without_history_has_no_stats()
    {
        $this->assertNull(app(SchemeStats::class)->for($this->scheme));
    }
}
