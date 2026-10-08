<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Converts between rupee strings entered by users and integer paise.
 */
final class Money
{
    /**
     * Convert a rupee amount such as "10000", "499.5" or "1,00,000.25" to paise.
     *
     * @throws InvalidArgumentException when the value isn't a non-negative amount with at most 2 decimals
     */
    public static function rupeesToPaise(string $rupees): int
    {
        $value = self::normalize($rupees);

        if (! self::isPlainAmount($value)) {
            throw new InvalidArgumentException("Not a rupee amount: [{$rupees}].");
        }

        $paise = bcmul($value, '100', 0);

        if (bccomp($paise, (string) PHP_INT_MAX) > 0) {
            throw new InvalidArgumentException("Rupee amount too large: [{$rupees}].");
        }

        return (int) $paise;
    }

    /**
     * Format paise as rupees with Indian digit grouping, e.g. 1234567 → "₹12,345.67",
     * -500 → "−₹5.00". Integer arithmetic only.
     */
    public static function formatInr(int $paise, bool $signed = false): string
    {
        $sign = $paise < 0 ? '−' : ($signed && $paise > 0 ? '+' : '');
        $absolute = abs($paise);
        $rupees = (string) intdiv($absolute, 100);
        $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        $lastThree = substr($rupees, -3);
        $rest = substr($rupees, 0, -3);
        $grouped = $rest === '' ? $lastThree : preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$lastThree;

        return "{$sign}₹{$grouped}.{$fraction}";
    }

    /**
     * Whether the string is an amount rupeesToPaise() accepts.
     */
    public static function isRupeeAmount(string $rupees): bool
    {
        return self::isPlainAmount(self::normalize($rupees));
    }

    private static function normalize(string $rupees): string
    {
        return str_replace([',', ' '], '', trim($rupees));
    }

    /**
     * @phpstan-assert-if-true numeric-string $value
     */
    private static function isPlainAmount(string $value): bool
    {
        return preg_match('/^\d+(\.\d{1,2})?$/', $value) === 1;
    }
}
