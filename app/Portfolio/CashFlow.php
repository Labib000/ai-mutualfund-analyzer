<?php

namespace App\Portfolio;

use Carbon\CarbonImmutable;

/**
 * Money moving between the investor and a fund on a date. Negative amounts
 * are money invested, positive amounts are money received (or current value).
 */
final readonly class CashFlow
{
    public function __construct(
        public CarbonImmutable $date,
        public int $amountPaise,
    ) {}
}
