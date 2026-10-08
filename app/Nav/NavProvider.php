<?php

namespace App\Nav;

use DateTimeImmutable;

/**
 * A source of historical NAVs for a scheme.
 */
interface NavProvider
{
    /**
     * Published NAVs from the given date (or from inception when null) up to today.
     *
     * @return array<string, string> NAVs rounded to 4 decimals, keyed by Y-m-d date, oldest first
     *
     * @throws NavProviderException when the source is unreachable, doesn't know the scheme, or returns malformed data
     */
    public function history(int $amfiCode, ?DateTimeImmutable $from = null): array;
}
