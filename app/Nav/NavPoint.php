<?php

namespace App\Nav;

use Carbon\CarbonImmutable;

/**
 * A NAV together with the date it was published for, which may differ from the date asked about.
 */
final readonly class NavPoint
{
    public function __construct(
        public CarbonImmutable $date,
        public string $nav,
    ) {}
}
