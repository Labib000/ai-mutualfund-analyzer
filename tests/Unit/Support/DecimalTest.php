<?php

namespace Tests\Unit\Support;

use App\Support\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    /**
     * @return array<string, array{string, int, string}>
     */
    public static function roundingCases(): array
    {
        return [
            'drops trailing zero' => ['29.23040', 4, '29.2304'],
            'rounds down' => ['14.86269632', 4, '14.8627'],
            'exactly half rounds up' => ['1.00005', 4, '1.0001'],
            'just below half rounds down' => ['1.000049999', 4, '1.0000'],
            'pads to scale' => ['12', 4, '12.0000'],
            'carries across digits' => ['9.99995', 4, '10.0000'],
            'large value' => ['2540201.95084999', 4, '2540201.9508'],
            'negative half rounds away from zero' => ['-1.00005', 4, '-1.0001'],
            'negative rounding to zero has no sign' => ['-0.00001', 4, '0.0000'],
            'zero scale' => ['2.5', 0, '3'],
            'units scale' => ['123.45650', 3, '123.457'],
        ];
    }

    #[DataProvider('roundingCases')]
    public function test_round(string $value, int $scale, string $expected)
    {
        $this->assertSame($expected, Decimal::round($value, $scale));
    }

    public function test_round_rejects_non_numeric_input()
    {
        $this->expectException(InvalidArgumentException::class);

        Decimal::round('N.A.', 4);
    }

    public function test_is_numeric()
    {
        $this->assertTrue(Decimal::isNumeric('29.2304'));
        $this->assertTrue(Decimal::isNumeric('-3'));
        $this->assertFalse(Decimal::isNumeric('N.A.'));
        $this->assertFalse(Decimal::isNumeric('1e5'));
        $this->assertFalse(Decimal::isNumeric('.5'));
        $this->assertFalse(Decimal::isNumeric(''));
    }
}
