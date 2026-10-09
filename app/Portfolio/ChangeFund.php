<?php

namespace App\Portfolio;

/**
 * One fund's part in a portfolio change. Money is in paise.
 */
final readonly class ChangeFund
{
    public function __construct(
        public string $name,
        public int $startPaise,
        public int $endPaise,
        public int $netFlowPaise,
    ) {}

    /** The part of the change not explained by money added or taken out. */
    public function marketPaise(): int
    {
        return $this->endPaise - $this->startPaise - $this->netFlowPaise;
    }

    /** Market movement against the starting value, or null for a fund bought during the period. */
    public function marketPct(): ?float
    {
        return $this->startPaise > 0 ? $this->marketPaise() / $this->startPaise * 100 : null;
    }

    /**
     * @return array{name: string, start_paise: int, end_paise: int, net_flow_paise: int, market_paise: int, market_pct: ?float}
     */
    public function toArray(): array
    {
        $pct = $this->marketPct();

        return [
            'name' => $this->name,
            'start_paise' => $this->startPaise,
            'end_paise' => $this->endPaise,
            'net_flow_paise' => $this->netFlowPaise,
            'market_paise' => $this->marketPaise(),
            'market_pct' => $pct === null ? null : round($pct, 2),
        ];
    }
}
