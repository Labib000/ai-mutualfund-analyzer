<?php

namespace App\Portfolio;

use App\Support\Decimal;
use DomainException;

/**
 * Tracks purchase lots so redemptions sell the oldest units first, giving
 * the cost of units still held and the gain realised on units sold.
 *
 * Callers add movements in date order, purchases before redemptions on the same day.
 */
final class FifoLots
{
    /** @var list<array{units: numeric-string, cost: int}> */
    private array $lots = [];

    private int $realisedGainPaise = 0;

    /**
     * @param  numeric-string  $units
     */
    public function buy(string $units, int $costPaise): void
    {
        $this->lots[] = ['units' => $units, 'cost' => $costPaise];
    }

    /**
     * @param  numeric-string  $units
     *
     * @throws DomainException when selling more units than are held
     */
    public function sell(string $units, int $proceedsPaise): void
    {
        $remaining = $units;
        $costRemoved = 0;

        while (bccomp($remaining, '0', UnitCalculator::UNITS_SCALE) > 0) {
            if ($this->lots === []) {
                throw new DomainException("Cannot sell {$units} units: only part of them are held.");
            }

            $lot = $this->lots[0];

            if (bccomp($lot['units'], $remaining, UnitCalculator::UNITS_SCALE) <= 0) {
                // The whole lot goes, with exactly its remaining cost, so rounding never accumulates.
                $costRemoved += $lot['cost'];
                $remaining = bcsub($remaining, $lot['units'], UnitCalculator::UNITS_SCALE);
                array_shift($this->lots);

                continue;
            }

            $cost = (int) Decimal::round(bcdiv(bcmul((string) $lot['cost'], $remaining, 6), $lot['units'], 6), 0);
            $this->lots[0] = [
                'units' => bcsub($lot['units'], $remaining, UnitCalculator::UNITS_SCALE),
                'cost' => $lot['cost'] - $cost,
            ];
            $costRemoved += $cost;
            $remaining = '0';
        }

        $this->realisedGainPaise += $proceedsPaise - $costRemoved;
    }

    /**
     * @return numeric-string
     */
    public function unitsHeld(): string
    {
        $units = '0';

        foreach ($this->lots as $lot) {
            $units = bcadd($units, $lot['units'], UnitCalculator::UNITS_SCALE);
        }

        return bcadd($units, '0', UnitCalculator::UNITS_SCALE);
    }

    /**
     * Cost of the units still held, in paise.
     */
    public function costPaise(): int
    {
        return array_sum(array_column($this->lots, 'cost'));
    }

    /**
     * Proceeds of redemptions minus the cost of the units they sold, in paise.
     */
    public function realisedGainPaise(): int
    {
        return $this->realisedGainPaise;
    }
}
