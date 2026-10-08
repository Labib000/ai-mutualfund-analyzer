<?php

namespace App\Portfolio;

use Carbon\CarbonImmutable;

/**
 * Money moving between the investor and a fund on a date. Negative amounts
 * are money invested, positive amounts are money received (or current value).
 */
final readonly class CashFlow
{
    /** Days since 1970-01-01 for the local calendar date, so solvers can subtract plain integers. */
    public int $epochDay;

    public function __construct(
        public CarbonImmutable $date,
        public int $amountPaise,
    ) {
        $this->epochDay = (int) floor(($date->getTimestamp() + $date->getOffset()) / 86400);
    }
}
