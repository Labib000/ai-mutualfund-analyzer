<?php

namespace App\Nav;

use App\Support\Decimal;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Historical NAVs from mfapi.in, which mirrors AMFI data per scheme code.
 *
 * GET /mf/{code}?startDate=Y-m-d&endDate=Y-m-d returns
 * {"meta": {"scheme_code": 135762, ...}, "data": [{"date": "07-10-2026", "nav": "29.23040"}, ...]}
 * newest first. Unknown codes return 200 with scheme_code 0 and no data.
 */
class MfApiNavProvider implements NavProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds,
    ) {}

    public function history(int $amfiCode, ?DateTimeImmutable $from = null): array
    {
        $query = $from === null ? [] : [
            'startDate' => $from->format('Y-m-d'),
            'endDate' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        ];

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeoutSeconds)
                ->retry(2, 500)
                ->acceptJson()
                ->get("/mf/{$amfiCode}", $query)
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new NavProviderException("mfapi.in request for scheme {$amfiCode} failed: {$e->getMessage()}", previous: $e);
        }

        $body = $response->json();

        if (! is_array($body) || ! is_array($body['data'] ?? null) || ! is_array($body['meta'] ?? null)) {
            throw new NavProviderException("mfapi.in returned a malformed response for scheme {$amfiCode}.");
        }

        if ((int) ($body['meta']['scheme_code'] ?? 0) !== $amfiCode) {
            throw new NavProviderException("mfapi.in does not know scheme {$amfiCode}.");
        }

        return $this->parseNavs($amfiCode, $body['data']);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, string>
     */
    private function parseNavs(int $amfiCode, array $data): array
    {
        $navs = [];

        foreach ($data as $point) {
            $date = is_array($point) && is_string($point['date'] ?? null)
                ? DateTimeImmutable::createFromFormat('!d-m-Y', $point['date'])
                : false;
            $nav = is_array($point) && is_string($point['nav'] ?? null) ? $point['nav'] : '';

            if ($date === false || ! Decimal::isNumeric($nav)) {
                throw new NavProviderException("mfapi.in returned a malformed NAV entry for scheme {$amfiCode}.");
            }

            // Skip placeholder zero NAVs, which segregated portfolios report.
            if (bccomp($nav, '0', 8) > 0) {
                $navs[$date->format('Y-m-d')] = Decimal::round($nav, 4);
            }
        }

        ksort($navs);

        return $navs;
    }
}
