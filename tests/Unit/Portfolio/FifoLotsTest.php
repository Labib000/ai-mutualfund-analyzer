<?php

namespace Tests\Unit\Portfolio;

use App\Portfolio\FifoLots;
use DomainException;
use PHPUnit\Framework\TestCase;

class FifoLotsTest extends TestCase
{
    public function test_purchases_only()
    {
        $lots = new FifoLots;
        $lots->buy('100.000', 100000);
        $lots->buy('50.500', 60000);

        $this->assertSame('150.500', $lots->unitsHeld());
        $this->assertSame(160000, $lots->costPaise());
        $this->assertSame(0, $lots->realisedGainPaise());
    }

    public function test_a_partial_redemption_sells_the_oldest_lot_first()
    {
        $lots = new FifoLots;
        $lots->buy('100.000', 100000); // ₹10 a unit
        $lots->buy('100.000', 200000); // ₹20 a unit
        $lots->sell('40.000', 100000);

        $this->assertSame('160.000', $lots->unitsHeld());
        $this->assertSame(60000 + 200000, $lots->costPaise());
        $this->assertSame(100000 - 40000, $lots->realisedGainPaise());
    }

    public function test_a_redemption_spanning_two_lots()
    {
        $lots = new FifoLots;
        $lots->buy('100.000', 100000);
        $lots->buy('100.000', 200000);
        $lots->sell('150.000', 450000);

        // All of lot 1 (₹1,000) and half of lot 2 (₹1,000).
        $this->assertSame('50.000', $lots->unitsHeld());
        $this->assertSame(100000, $lots->costPaise());
        $this->assertSame(450000 - 200000, $lots->realisedGainPaise());
    }

    public function test_a_full_redemption_leaves_no_cost()
    {
        $lots = new FifoLots;
        $lots->buy('343.507', 1000000);
        $lots->buy('171.316', 500000);
        $lots->sell('514.823', 1600000);

        $this->assertSame('0.000', $lots->unitsHeld());
        $this->assertSame(0, $lots->costPaise());
        $this->assertSame(100000, $lots->realisedGainPaise());
    }

    public function test_rounding_never_leaves_a_remainder_on_a_consumed_lot()
    {
        $lots = new FifoLots;
        $lots->buy('3.000', 100); // ₹1 for 3 units: a third of a paisa each

        $lots->sell('1.000', 40); // removes 33 paise (rounded)
        $lots->sell('1.000', 40); // removes 33 or 34 paise
        $lots->sell('1.000', 40); // removes exactly what is left

        $this->assertSame(0, $lots->costPaise());
        $this->assertSame(120 - 100, $lots->realisedGainPaise());
    }

    public function test_selling_more_than_held_is_an_error()
    {
        $lots = new FifoLots;
        $lots->buy('10.000', 10000);

        $this->expectException(DomainException::class);

        $lots->sell('10.001', 10000);
    }
}
