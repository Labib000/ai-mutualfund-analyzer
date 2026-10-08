<?php

namespace App\Portfolio;

use Carbon\CarbonImmutable;

/**
 * One purchase, SIP installment or redemption, as the calculators need it.
 */
final readonly class Movement
{
    /** The date as Y-m-d, computed once because sorting and replays compare it often. */
    public string $day;

    /**
     * @param  numeric-string  $units
     * @param  int  $amountPaise  Invested for purchases, received for redemptions
     */
    public function __construct(
        public CarbonImmutable $date,
        public bool $addsUnits,
        public string $units,
        public int $amountPaise,
    ) {
        $this->day = $date->toDateString();
    }
}
