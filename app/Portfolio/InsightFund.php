<?php

namespace App\Portfolio;

use App\Enums\SchemePlan;
use Carbon\CarbonImmutable;

/**
 * What the insight rules need to know about one fund.
 */
final readonly class InsightFund
{
    public function __construct(
        public string $name,
        public string $amc,
        public string $category,
        public ?SchemePlan $plan,
        public int $investedPaise,
        public int $valuePaise,
        public ?CarbonImmutable $lastTransaction,
        public bool $hasRunningSip,
    ) {}

    /** "Parag Parikh Flexi Cap Fund - Direct Plan - Growth" → "Parag Parikh Flexi Cap Fund". */
    public function shortName(): string
    {
        return explode(' - ', $this->name, 2)[0];
    }
}
