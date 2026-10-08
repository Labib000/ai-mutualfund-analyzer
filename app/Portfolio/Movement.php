<?php

namespace App\Portfolio;

use Carbon\CarbonImmutable;

/**
 * One purchase, SIP installment or redemption, as the calculators need it.
 */
final readonly class Movement
{
    /**
     * @param  numeric-string  $units
     * @param  int  $amountPaise  Invested for purchases, received for redemptions
     */
    public function __construct(
        public CarbonImmutable $date,
        public bool $addsUnits,
        public string $units,
        public int $amountPaise,
    ) {}
}
