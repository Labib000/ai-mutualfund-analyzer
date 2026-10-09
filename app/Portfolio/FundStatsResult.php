<?php

namespace App\Portfolio;

/**
 * A scheme's past returns and risk, from its NAV history. Returns, volatility
 * and drawdown are fractions (0.15 = 15%).
 */
final readonly class FundStatsResult
{
    public function __construct(
        public string $asOf,
        public string $since,
        public ?float $return1y,
        public ?float $cagr3y,
        public ?float $cagr5y,
        public float $sinceStart,
        public bool $sinceStartAnnualised,
        public ?float $volatility,
        public float $maxDrawdown,
        public ?string $drawdownPeak,
        public ?string $drawdownTrough,
    ) {}

    /**
     * Percentages rounded to 2 decimals, for the UI and the cache.
     *
     * @return array{as_of: string, since: string, return_1y_pct: ?float, cagr_3y_pct: ?float, cagr_5y_pct: ?float, since_start_pct: float, since_start_annualised: bool, volatility_pct: ?float, max_drawdown_pct: float, drawdown_peak: ?string, drawdown_trough: ?string}
     */
    public function toArray(): array
    {
        return [
            'as_of' => $this->asOf,
            'since' => $this->since,
            'return_1y_pct' => self::pct($this->return1y),
            'cagr_3y_pct' => self::pct($this->cagr3y),
            'cagr_5y_pct' => self::pct($this->cagr5y),
            'since_start_pct' => (float) self::pct($this->sinceStart),
            'since_start_annualised' => $this->sinceStartAnnualised,
            'volatility_pct' => self::pct($this->volatility),
            'max_drawdown_pct' => (float) self::pct($this->maxDrawdown),
            'drawdown_peak' => $this->drawdownPeak,
            'drawdown_trough' => $this->drawdownTrough,
        ];
    }

    /**
     * @param  array{as_of: string, since: string, return_1y_pct: ?float, cagr_3y_pct: ?float, cagr_5y_pct: ?float, since_start_pct: float, since_start_annualised: bool, volatility_pct: ?float, max_drawdown_pct: float, drawdown_peak: ?string, drawdown_trough: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            asOf: $data['as_of'],
            since: $data['since'],
            return1y: self::fraction($data['return_1y_pct']),
            cagr3y: self::fraction($data['cagr_3y_pct']),
            cagr5y: self::fraction($data['cagr_5y_pct']),
            sinceStart: $data['since_start_pct'] / 100,
            sinceStartAnnualised: $data['since_start_annualised'],
            volatility: self::fraction($data['volatility_pct']),
            maxDrawdown: $data['max_drawdown_pct'] / 100,
            drawdownPeak: $data['drawdown_peak'],
            drawdownTrough: $data['drawdown_trough'],
        );
    }

    private static function pct(?float $fraction): ?float
    {
        return $fraction === null ? null : round($fraction * 100, 2);
    }

    private static function fraction(?float $pct): ?float
    {
        return $pct === null ? null : $pct / 100;
    }
}
