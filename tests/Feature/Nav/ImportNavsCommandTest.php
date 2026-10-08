<?php

namespace Tests\Feature\Nav;

use App\Models\NavHistory;
use App\Models\Scheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ImportNavsCommandTest extends TestCase
{
    use RefreshDatabase;

    private const AMFI_URL = 'https://www.amfiindia.com/spages/NAVAll.txt';

    private string $fixture;

    /** Body served for the AMFI URL by importFixture(). */
    private ?string $served = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.amfi.nav_url' => self::AMFI_URL]);
        $this->fixture = (string) file_get_contents(base_path('tests/Fixtures/amfi/NAVAll-sample.txt'));

        Sleep::fake();
    }

    public function test_it_downloads_and_imports_schemes()
    {
        Http::fake([self::AMFI_URL => Http::response($this->fixture)]);

        $this->artisan('nav:import')
            ->expectsOutputToContain('Imported AMFI NAVs dated 07 Oct 2026.')
            ->assertSuccessful();

        $this->assertSame(11, Scheme::count());

        $scheme = Scheme::where('amfi_code', 135762)->sole();
        $this->assertSame("Axis Children's Fund - Direct Plan - Growth Option", $scheme->name);
        $this->assertSame('29.2304', $scheme->latest_nav);
        $this->assertSame('2026-10-07', $scheme->latest_nav_date?->toDateString());
        $this->assertTrue($scheme->is_active);
    }

    public function test_etfs_are_not_imported()
    {
        $this->importFixture($this->fixture);

        $this->assertDatabaseMissing('schemes', ['amfi_code' => 151533]);
    }

    public function test_schemes_without_a_recent_nav_are_inactive()
    {
        $this->importFixture($this->fixture);

        $this->assertFalse(Scheme::where('amfi_code', 134568)->sole()->is_active);
        $this->assertFalse(Scheme::where('amfi_code', 133786)->sole()->is_active);
    }

    public function test_running_twice_does_not_duplicate_schemes()
    {
        $this->importFixture($this->fixture);
        $this->importFixture($this->fixture);

        $this->assertSame(11, Scheme::count());
    }

    public function test_a_newer_file_updates_navs_and_activity()
    {
        $this->importFixture($this->fixture);

        $this->importFixture(str_replace(
            'Growth Option;29.2304;07-Oct-2026',
            'Growth Option;30.1000;20-Nov-2026',
            $this->fixture,
        ));

        $updated = Scheme::where('amfi_code', 135762)->sole();
        $this->assertSame('30.1000', $updated->latest_nav);
        $this->assertSame('2026-11-20', $updated->latest_nav_date?->toDateString());
        $this->assertTrue($updated->is_active);

        // Every other scheme is now more than 30 days behind the file date.
        $this->assertFalse(Scheme::where('amfi_code', 119133)->sole()->is_active);
    }

    public function test_an_unpublished_nav_keeps_the_last_known_nav()
    {
        $this->importFixture($this->fixture);

        $this->importFixture(str_replace(
            'Growth Option;29.2304;07-Oct-2026',
            'Growth Option;N.A.;08-Oct-2026',
            $this->fixture,
        ));

        $scheme = Scheme::where('amfi_code', 135762)->sole();
        $this->assertSame('29.2304', $scheme->latest_nav);
        $this->assertSame('2026-10-07', $scheme->latest_nav_date?->toDateString());
    }

    public function test_tracked_schemes_get_the_nav_appended_to_history_once()
    {
        $this->importFixture($this->fixture);
        Scheme::where('amfi_code', 135762)->update(['history_synced_at' => now()]);

        $this->importFixture($this->fixture);
        $this->importFixture($this->fixture);

        $history = NavHistory::all();
        $this->assertCount(1, $history);
        $this->assertSame('2026-10-07', $history[0]->nav_date->toDateString());
        $this->assertSame('29.2304', $history[0]->nav);
    }

    public function test_untracked_schemes_get_no_history()
    {
        $this->importFixture($this->fixture);

        $this->assertSame(0, NavHistory::count());
    }

    public function test_it_fails_when_amfi_is_down()
    {
        Http::fake([self::AMFI_URL => Http::response('Service unavailable', 503)]);

        $this->artisan('nav:import')
            ->expectsOutputToContain('Could not download the AMFI NAV file')
            ->assertFailed();

        $this->assertSame(0, Scheme::count());
    }

    public function test_it_fails_on_a_page_that_is_not_a_nav_file()
    {
        Http::fake([self::AMFI_URL => Http::response('<html>Maintenance</html>')]);

        $this->artisan('nav:import')
            ->expectsOutputToContain('could not be imported')
            ->assertFailed();
    }

    public function test_it_imports_a_local_file()
    {
        Http::preventStrayRequests();

        $this->artisan('nav:import', ['--file' => base_path('tests/Fixtures/amfi/NAVAll-sample.txt')])
            ->assertSuccessful();

        $this->assertSame(11, Scheme::count());
    }

    public function test_it_fails_on_a_missing_local_file()
    {
        $this->artisan('nav:import', ['--file' => '/nonexistent/NAVAll.txt'])
            ->assertFailed();
    }

    private function importFixture(string $contents): void
    {
        // Http::fake() keeps the first stub for a URL, so register one stub that serves the latest body.
        if ($this->served === null) {
            Http::fake([self::AMFI_URL => fn () => Http::response($this->served)]);
        }

        $this->served = $contents;

        $this->artisan('nav:import')->assertSuccessful();
    }
}
