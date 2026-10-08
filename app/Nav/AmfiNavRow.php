<?php

namespace App\Nav;

use App\Enums\SchemePlan;
use App\Enums\SchemeType;
use DateTimeImmutable;

/**
 * One scheme line from AMFI's NAVAll.txt, with the section and AMC it sits under.
 */
final readonly class AmfiNavRow
{
    public function __construct(
        public int $amfiCode,
        public ?string $isinGrowth,
        public ?string $isinReinvestment,
        public string $name,
        public string $amc,
        public SchemeType $schemeType,
        public string $category,
        public ?SchemePlan $plan,
        /** NAV rounded to 4 decimals, or null when not published or not positive. */
        public ?string $nav,
        public ?DateTimeImmutable $navDate,
    ) {}

    public function isEtf(): bool
    {
        // ETFs appear under "Exchange Traded Funds (ETFs) - ..." and older "Other Scheme - Gold ETF" sections.
        return preg_match('/\bETFs?\b/', $this->category) === 1;
    }
}
