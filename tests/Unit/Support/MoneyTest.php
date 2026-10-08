<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function validAmounts(): array
    {
        return [
            'whole rupees' => ['10000', 1000000],
            'one decimal' => ['499.5', 49950],
            'two decimals' => ['0.01', 1],
            'indian grouping' => ['1,00,000.25', 10000025],
            'surrounding spaces' => [' 250 ', 25000],
            'zero' => ['0', 0],
        ];
    }

    #[DataProvider('validAmounts')]
    public function test_rupees_to_paise(string $rupees, int $paise)
    {
        $this->assertSame($paise, Money::rupeesToPaise($rupees));
        $this->assertTrue(Money::isRupeeAmount($rupees));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'three decimals' => ['1.005'],
            'negative' => ['-5'],
            'text' => ['ten'],
            'empty' => [''],
            'exponent' => ['1e5'],
            'bare decimal point' => ['.5'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amounts_are_rejected(string $rupees)
    {
        $this->assertFalse(Money::isRupeeAmount($rupees));

        $this->expectException(InvalidArgumentException::class);
        Money::rupeesToPaise($rupees);
    }

    public function test_amounts_beyond_integer_range_are_rejected()
    {
        $this->expectException(InvalidArgumentException::class);

        Money::rupeesToPaise('999999999999999999999');
    }
}
