<?php

namespace App\Portfolio;

use App\Enums\XirrStatus;
use Carbon\CarbonImmutable;

/**
 * Returns for one fund or a whole portfolio. Money is in paise.
 */
final readonly class Performance
{
    /**
     * @param  numeric-string|null  $unitsHeld  Null for a portfolio, which mixes funds
     */
    public function __construct(
        public ?string $unitsHeld,
        public int $investedPaise,
        public int $valuePaise,
        public int $realisedGainPaise,
        public ?float $xirr,
        public XirrStatus $xirrStatus,
        public ?CarbonImmutable $valuedOn,
    ) {}

    /** Current value minus the cost of units held. */
    public function unrealisedGainPaise(): int
    {
        return $this->valuePaise - $this->investedPaise;
    }

    public function totalGainPaise(): int
    {
        return $this->unrealisedGainPaise() + $this->realisedGainPaise;
    }

    /** Unrealised gain as a percentage of the cost of units held, or null when nothing is held. */
    public function absoluteReturnPct(): ?float
    {
        return $this->investedPaise > 0 ? $this->unrealisedGainPaise() / $this->investedPaise * 100 : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $absoluteReturn = $this->absoluteReturnPct();

        return [
            'units_held' => $this->unitsHeld,
            'invested_paise' => $this->investedPaise,
            'value_paise' => $this->valuePaise,
            'unrealised_gain_paise' => $this->unrealisedGainPaise(),
            'realised_gain_paise' => $this->realisedGainPaise,
            'total_gain_paise' => $this->totalGainPaise(),
            'absolute_return_pct' => $absoluteReturn === null ? null : round($absoluteReturn, 2),
            'xirr_pct' => $this->xirr === null ? null : round($this->xirr * 100, 2),
            'xirr_status' => $this->xirrStatus->value,
            'valued_on' => $this->valuedOn?->toDateString(),
        ];
    }
}
