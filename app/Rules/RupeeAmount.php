<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A rupee amount with at most 2 decimals, within limits given in paise.
 */
class RupeeAmount implements ValidationRule
{
    public function __construct(
        private readonly int $minPaise = 100,
        private readonly int $maxPaise = 10_000_000_000,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('The :attribute must be an amount in rupees.');

            return;
        }

        if (! Money::isRupeeAmount((string) $value)) {
            $fail('The :attribute must be an amount in rupees with at most 2 decimals.');

            return;
        }

        $paise = Money::rupeesToPaise((string) $value);

        if ($paise < $this->minPaise) {
            $fail('The :attribute must be at least '.self::format($this->minPaise).'.');
        } elseif ($paise > $this->maxPaise) {
            $fail('The :attribute must not be more than '.self::format($this->maxPaise).'.');
        }
    }

    private static function format(int $paise): string
    {
        return '₹'.number_format($paise / 100, $paise % 100 === 0 ? 0 : 2);
    }
}
