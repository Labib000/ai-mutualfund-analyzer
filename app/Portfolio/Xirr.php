<?php

namespace App\Portfolio;

/**
 * Annualised internal rate of return for irregular cash flows, using Excel's
 * XIRR() convention: the rate r where Σ amount / (1 + r)^((date − first date) / 365) = 0.
 *
 * The result is a rate, not money, so the solver works in floats.
 */
final class Xirr
{
    private const DAYS_PER_YEAR = 365;

    private const MAX_NEWTON_ITERATIONS = 100;

    private const MAX_BISECTION_ITERATIONS = 300;

    private const TOLERANCE = 1e-10;

    private const LOWEST_RATE = -0.999999999;

    private const HIGHEST_RATE = 1e6;

    /**
     * @param  list<CashFlow>  $flows
     * @return float|null The rate as a fraction (0.15 = 15%), or null when the flows have no meaningful rate
     */
    public function rate(array $flows): ?float
    {
        $flows = array_values(array_filter($flows, fn (CashFlow $flow) => $flow->amountPaise !== 0));

        if ($flows === [] || ! $this->hasBothSigns($flows)) {
            return null;
        }

        $start = min(array_map(fn (CashFlow $flow) => $flow->epochDay, $flows));

        $points = array_map(fn (CashFlow $flow) => [
            $flow->amountPaise / 100,
            ($flow->epochDay - $start) / self::DAYS_PER_YEAR,
        ], $flows);

        return $this->newton($points) ?? $this->bisection($points);
    }

    /**
     * @param  list<CashFlow>  $flows
     */
    private function hasBothSigns(array $flows): bool
    {
        $negative = false;
        $positive = false;

        foreach ($flows as $flow) {
            $negative = $negative || $flow->amountPaise < 0;
            $positive = $positive || $flow->amountPaise > 0;
        }

        return $negative && $positive;
    }

    /**
     * @param  list<array{float, float}>  $points  [amount, years since the first flow]
     */
    private function newton(array $points): ?float
    {
        $rate = 0.1;

        for ($i = 0; $i < self::MAX_NEWTON_ITERATIONS; $i++) {
            $value = 0.0;
            $derivative = 0.0;

            foreach ($points as [$amount, $years]) {
                $discount = (1 + $rate) ** -$years;
                $value += $amount * $discount;
                $derivative -= $years * $amount * $discount / (1 + $rate);
            }

            if ($derivative == 0.0 || ! is_finite($value) || ! is_finite($derivative)) {
                return null;
            }

            $next = $rate - $value / $derivative;

            if (! is_finite($next) || $next <= -1) {
                return null;
            }

            if (abs($next - $rate) < self::TOLERANCE) {
                return $next;
            }

            $rate = $next;
        }

        return null;
    }

    /**
     * Fallback when Newton's method doesn't converge: bracket a sign change, then halve it.
     *
     * @param  list<array{float, float}>  $points
     */
    private function bisection(array $points): ?float
    {
        $low = self::LOWEST_RATE;
        $high = 1.0;
        $lowValue = $this->presentValue($points, $low);
        $highValue = $this->presentValue($points, $high);

        while ($this->sameSign($lowValue, $highValue)) {
            if ($high >= self::HIGHEST_RATE) {
                return null;
            }

            $low = $high;
            $lowValue = $highValue;
            $high *= 2;
            $highValue = $this->presentValue($points, $high);
        }

        for ($i = 0; $i < self::MAX_BISECTION_ITERATIONS && $high - $low > self::TOLERANCE; $i++) {
            $mid = ($low + $high) / 2;
            $midValue = $this->presentValue($points, $mid);

            if ($this->sameSign($lowValue, $midValue)) {
                $low = $mid;
                $lowValue = $midValue;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2;
    }

    /**
     * @param  list<array{float, float}>  $points
     */
    private function presentValue(array $points, float $rate): float
    {
        $value = 0.0;

        foreach ($points as [$amount, $years]) {
            $value += $amount * (1 + $rate) ** -$years;
        }

        return $value;
    }

    private function sameSign(float $a, float $b): bool
    {
        return ($a > 0 && $b > 0) || ($a < 0 && $b < 0);
    }
}
