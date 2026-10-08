<?php

namespace Tests\Feature\Nav;

use App\Nav\MfApiNavProvider;
use App\Nav\NavProviderException;
use DateTimeImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class MfApiNavProviderTest extends TestCase
{
    private MfApiNavProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        $this->provider = new MfApiNavProvider('https://api.mfapi.in', 5);
    }

    public function test_it_returns_navs_oldest_first_rounded_to_four_decimals()
    {
        Http::fake(['api.mfapi.in/mf/135762*' => Http::response($this->body(135762, [
            ['date' => '07-10-2026', 'nav' => '29.23040'],
            ['date' => '06-10-2026', 'nav' => '29.33690'],
            ['date' => '01-10-2026', 'nav' => '29.000149'],
        ]))]);

        $this->assertSame([
            '2026-10-01' => '29.0001',
            '2026-10-06' => '29.3369',
            '2026-10-07' => '29.2304',
        ], $this->provider->history(135762));
    }

    public function test_it_requests_the_full_history_without_a_start_date()
    {
        Http::fake(['api.mfapi.in/*' => Http::response($this->body(135762, []))]);

        $this->provider->history(135762);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.mfapi.in/mf/135762');
    }

    public function test_it_requests_a_range_from_the_start_date()
    {
        Http::fake(['api.mfapi.in/*' => Http::response($this->body(135762, []))]);

        $this->provider->history(135762, new DateTimeImmutable('2026-09-01'));

        Http::assertSent(fn (Request $request) => $request['startDate'] === '2026-09-01'
            && $request['endDate'] === (new DateTimeImmutable('today'))->format('Y-m-d'));
    }

    public function test_it_skips_zero_navs()
    {
        Http::fake(['api.mfapi.in/*' => Http::response($this->body(148239, [
            ['date' => '07-10-2026', 'nav' => '0.00000'],
            ['date' => '06-10-2026', 'nav' => '10.50000'],
        ]))]);

        $this->assertSame(['2026-10-06' => '10.5000'], $this->provider->history(148239));
    }

    public function test_an_unknown_scheme_throws()
    {
        Http::fake(['api.mfapi.in/*' => Http::response($this->body(0, []))]);

        $this->expectException(NavProviderException::class);
        $this->expectExceptionMessage('does not know scheme 999999');

        $this->provider->history(999999);
    }

    public function test_a_server_error_throws_after_retrying()
    {
        Http::fake(['api.mfapi.in/*' => Http::response('Bad gateway', 502)]);

        try {
            $this->provider->history(135762);
            $this->fail('Expected a NavProviderException.');
        } catch (NavProviderException) {
            Http::assertSentCount(2);
        }
    }

    public function test_a_connection_failure_throws()
    {
        Http::fake(['api.mfapi.in/*' => Http::failedConnection()]);

        $this->expectException(NavProviderException::class);

        $this->provider->history(135762);
    }

    public function test_a_malformed_body_throws()
    {
        Http::fake(['api.mfapi.in/*' => Http::response('<html>oops</html>')]);

        $this->expectException(NavProviderException::class);

        $this->provider->history(135762);
    }

    public function test_a_malformed_entry_throws()
    {
        Http::fake(['api.mfapi.in/*' => Http::response($this->body(135762, [
            ['date' => '2026-10-07', 'nav' => '29.2304'],
        ]))]);

        $this->expectException(NavProviderException::class);

        $this->provider->history(135762);
    }

    /**
     * @param  list<array{date: string, nav: string}>  $data
     * @return array<string, mixed>
     */
    private function body(int $code, array $data): array
    {
        return [
            'meta' => ['scheme_code' => $code, 'scheme_name' => 'Test Fund'],
            'data' => $data,
            'status' => 'SUCCESS',
        ];
    }
}
