<?php

namespace App\Http\Requests\Portfolio;

use App\Portfolio\UnitCalculator;
use App\Support\Decimal;

/**
 * Normalizes a validated units field to a 3-decimal string.
 */
final class UnitsInput
{
    /**
     * @return numeric-string|null
     */
    public static function from(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return Decimal::isNumeric($value) ? Decimal::round($value, UnitCalculator::UNITS_SCALE) : null;
    }
}
