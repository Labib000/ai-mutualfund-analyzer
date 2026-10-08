<?php

namespace App\Portfolio;

use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Converts between money and units the way registrars do.
 */
final class UnitCalculator
{
    /** Stamp duty on mutual fund purchases since 1 July 2020: 0.005%. */
    private const STAMP_DUTY_PER_100000 = 5;

    public const UNITS_SCALE = 3;

    /**
     * Stamp duty on a purchase, rounded half-up to the paisa (₹10,000 → 50 paise).
     */
    public function stampDuty(int $amountPaise): int
    {
        if ($amountPaise < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        return intdiv($amountPaise * self::STAMP_DUTY_PER_100000 + 50000, 100000);
    }

    /**
     * Units allotted for a purchase: (amount − stamp duty) ÷ NAV, rounded to 3 decimals.
     *
     * @return numeric-string
     */
    public function purchaseUnits(int $amountPaise, int $stampDutyPaise, string $nav): string
    {
        $nav = $this->positive($nav, 'NAV');

        if ($stampDutyPaise < 0 || $stampDutyPaise > $amountPaise) {
            throw new InvalidArgumentException('Stamp duty must be between zero and the amount.');
        }

        $netRupees = bcdiv((string) ($amountPaise - $stampDutyPaise), '100', 2);

        return Decimal::round(bcdiv($netRupees, $nav, 10), self::UNITS_SCALE);
    }

    /**
     * Value of units at a NAV, in paise rounded half-up.
     */
    public function redemptionAmount(string $units, string $nav): int
    {
        $units = $this->positive($units, 'Units');
        $nav = $this->positive($nav, 'NAV');

        return (int) Decimal::round(bcmul(bcmul($units, $nav, 7), '100', 5), 0);
    }

    /**
     * @return numeric-string
     */
    private function positive(string $value, string $name): string
    {
        if (! Decimal::isNumeric($value) || bccomp($value, '0', 8) <= 0) {
            throw new InvalidArgumentException("{$name} must be a positive decimal number.");
        }

        return $value;
    }
}
