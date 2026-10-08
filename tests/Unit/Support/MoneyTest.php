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

    public function test_format_inr_uses_indian_grouping()
    {
        $this->assertSame('₹0.05', Money::formatInr(5));
        $this->assertSame('₹999.00', Money::formatInr(99900));
        $this->assertSame('₹1,000.00', Money::formatInr(100000));
        $this->assertSame('₹12,345.67', Money::formatInr(1234567));
        $this->assertSame('₹1,00,000.00', Money::formatInr(10000000));
        $this->assertSame('₹12,34,56,789.00', Money::formatInr(123456789_00));
        $this->assertSame('−₹116.08', Money::formatInr(-11608));
        $this->assertSame('+₹682.04', Money::formatInr(68204, signed: true));
        $this->assertSame('₹0.00', Money::formatInr(0, signed: true));
    }
}
