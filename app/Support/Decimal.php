<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic on numeric strings, backed by bcmath.
 *
 * NAVs and units must never pass through floats. bcround() only exists from
 * PHP 8.4, and production may run 8.3, so rounding is implemented here.
 */
final class Decimal
{
    /**
     * Round half away from zero to the given number of decimal places.
     *
     * @return numeric-string
     */
    public static function round(string $value, int $scale): string
    {
        $value = trim($value);

        if (! self::isNumeric($value)) {
            throw new InvalidArgumentException("Not a decimal number: [{$value}].");
        }

        if ($scale < 0) {
            throw new InvalidArgumentException('Scale must be zero or greater.');
        }

        $half = bcdiv('5', bcpow('10', (string) ($scale + 1)), $scale + 1);
        $adjusted = str_starts_with($value, '-')
            ? bcsub($value, $half, $scale + 1)
            : bcadd($value, $half, $scale + 1);

        // bcadd truncates towards zero, which after adding half gives half-up.
        $rounded = bcadd($adjusted, '0', $scale);

        return self::isZero($rounded) ? bcadd('0', '0', $scale) : $rounded;
    }

    /**
     * Whether the string is a plain decimal number such as "12", "-0.5" or "29.23040".
     *
     * @phpstan-assert-if-true numeric-string $value
     */
    public static function isNumeric(string $value): bool
    {
        return preg_match('/^-?\d+(\.\d+)?$/', $value) === 1;
    }

    /**
     * @param  numeric-string  $value
     */
    private static function isZero(string $value): bool
    {
        return bccomp($value, '0', 20) === 0;
    }
}
