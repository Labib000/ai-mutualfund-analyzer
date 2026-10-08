<?php

namespace Tests\Unit\Portfolio;

use App\Portfolio\UnitCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UnitCalculatorTest extends TestCase
{
    private UnitCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new UnitCalculator;
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function stampDutyCases(): array
    {
        return [
            '₹10,000' => [1000000, 50],
            '₹500 rounds 2.5 paise up' => [50000, 3],
            '₹499 rounds 2.495 paise down' => [49900, 2],
            '₹1' => [100, 0],
            'zero' => [0, 0],
            '₹10 crore' => [10000000000, 500000],
        ];
    }

    #[DataProvider('stampDutyCases')]
    public function test_stamp_duty(int $amountPaise, int $expected)
    {
        $this->assertSame($expected, $this->calculator->stampDuty($amountPaise));
    }

    public function test_purchase_units_deduct_stamp_duty_and_round_to_three_decimals()
    {
        $this->assertSame('343.507', $this->calculator->purchaseUnits(1000000, 50, '29.1100'));
        $this->assertSame('17.175', $this->calculator->purchaseUnits(50000, 3, '29.1100'));
    }

    public function test_purchase_units_for_large_amounts()
    {
        $this->assertSame('999995.000', $this->calculator->purchaseUnits(1000000000, 5000, '10.0000'));
    }

    public function test_tiny_purchases_at_high_navs_round_to_zero_units()
    {
        $this->assertSame('0.000', $this->calculator->purchaseUnits(100, 0, '2540201.9508'));
    }

    public function test_purchase_units_reject_a_zero_nav()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->purchaseUnits(100000, 5, '0');
    }

    public function test_purchase_units_reject_stamp_duty_above_the_amount()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->purchaseUnits(100, 101, '10.0000');
    }

    public function test_redemption_amount_in_paise()
    {
        $this->assertSame(1004085, $this->calculator->redemptionAmount('343.507', '29.2304'));
        $this->assertSame(1000, $this->calculator->redemptionAmount('1.000', '10.0000'));
    }

    public function test_redemption_amount_rejects_zero_units()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->redemptionAmount('0.000', '10.0000');
    }
}
