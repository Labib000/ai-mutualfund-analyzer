<?php

namespace App\Portfolio;

use Carbon\CarbonImmutable;

/**
 * A fund's movements and the NAV to value it at.
 */
final readonly class HoldingInput
{
    /**
     * @param  list<Movement>  $movements
     * @param  numeric-string|null  $latestNav
     */
    public function __construct(
        public array $movements,
        public ?string $latestNav,
        public ?CarbonImmutable $latestNavDate,
    ) {}
}
