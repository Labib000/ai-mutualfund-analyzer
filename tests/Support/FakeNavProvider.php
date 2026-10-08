<?php

namespace Tests\Support;

use App\Nav\NavProvider;
use App\Nav\NavProviderException;
use DateTimeImmutable;

/**
 * In-memory NavProvider that records every call.
 */
class FakeNavProvider implements NavProvider
{
    /** @var list<array{int, ?string}> */
    public array $calls = [];

    public bool $failing = false;

    /**
     * @param  array<int, array<string, string>>  $navs  NAVs keyed by AMFI code, then Y-m-d date
     */
    public function __construct(public array $navs = []) {}

    public function history(int $amfiCode, ?DateTimeImmutable $from = null): array
    {
        $this->calls[] = [$amfiCode, $from?->format('Y-m-d')];

        if ($this->failing) {
            throw new NavProviderException('Provider is down.');
        }

        $navs = array_filter(
            $this->navs[$amfiCode] ?? [],
            fn (string $date) => $from === null || $date >= $from->format('Y-m-d'),
            ARRAY_FILTER_USE_KEY,
        );
        ksort($navs);

        return $navs;
    }
}
