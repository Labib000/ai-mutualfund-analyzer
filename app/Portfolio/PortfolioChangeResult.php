<?php

namespace App\Portfolio;

use App\Enums\ChangePeriod;

/**
 * How a portfolio's value moved over a period, split into new money and market movement.
 */
final readonly class PortfolioChangeResult
{
    /**
     * @param  list<ChangeFund>  $funds  Funds held at some point in the period, largest end value first
     */
    public function __construct(
        public ChangePeriod $period,
        public string $from,
        public string $to,
        public array $funds,
        public int $sipInstallments,
    ) {}

    public function startPaise(): int
    {
        return array_sum(array_map(fn (ChangeFund $fund) => $fund->startPaise, $this->funds));
    }

    public function endPaise(): int
    {
        return array_sum(array_map(fn (ChangeFund $fund) => $fund->endPaise, $this->funds));
    }

    /** Purchases and SIP installments minus redemption proceeds in the period. */
    public function netFlowPaise(): int
    {
        return array_sum(array_map(fn (ChangeFund $fund) => $fund->netFlowPaise, $this->funds));
    }

    public function marketPaise(): int
    {
        return $this->endPaise() - $this->startPaise() - $this->netFlowPaise();
    }

    public function marketPct(): ?float
    {
        return $this->startPaise() > 0 ? $this->marketPaise() / $this->startPaise() * 100 : null;
    }

    /** The fund whose market movement was highest, among funds held at the start. */
    public function best(): ?ChangeFund
    {
        return $this->ranked()[0] ?? null;
    }

    /** The fund whose market movement was lowest, among funds held at the start; null with fewer than two. */
    public function worst(): ?ChangeFund
    {
        $ranked = $this->ranked();

        return count($ranked) > 1 ? $ranked[count($ranked) - 1] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $pct = $this->marketPct();

        return [
            'period' => $this->period->value,
            'label' => $this->period->label(),
            'from' => $this->from,
            'to' => $this->to,
            'start_paise' => $this->startPaise(),
            'end_paise' => $this->endPaise(),
            'net_flow_paise' => $this->netFlowPaise(),
            'market_paise' => $this->marketPaise(),
            'market_pct' => $pct === null ? null : round($pct, 2),
            'sip_installments' => $this->sipInstallments,
            'best' => $this->best()?->toArray(),
            'worst' => $this->worst()?->toArray(),
            'funds' => array_map(fn (ChangeFund $fund) => $fund->toArray(), $this->funds),
        ];
    }

    /**
     * Funds held at the start, by market movement as a percentage, highest first.
     *
     * @return list<ChangeFund>
     */
    private function ranked(): array
    {
        $ranked = array_values(array_filter($this->funds, fn (ChangeFund $fund) => $fund->startPaise > 0));
        usort($ranked, fn (ChangeFund $a, ChangeFund $b) => $b->marketPct() <=> $a->marketPct());

        return $ranked;
    }
}
