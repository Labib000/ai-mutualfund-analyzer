<?php

namespace Tests\Unit\Portfolio;

use App\Portfolio\UnitLedger;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UnitLedgerTest extends TestCase
{
    public function test_an_empty_ledger_holds_nothing()
    {
        $ledger = new UnitLedger;

        $this->assertSame('0.000', $ledger->unitsHeldOn('2026-10-01'));
        $this->assertNull($ledger->firstOverdrawnDate());
    }

    public function test_units_held_on_a_date_include_that_days_movements_only_up_to_it()
    {
        $ledger = (new UnitLedger)
            ->with('2026-01-10', '100.500', true)
            ->with('2026-02-10', '50.250', true)
            ->with('2026-03-10', '30.000', false);

        $this->assertSame('0.000', $ledger->unitsHeldOn('2026-01-09'));
        $this->assertSame('100.500', $ledger->unitsHeldOn('2026-01-10'));
        $this->assertSame('150.750', $ledger->unitsHeldOn('2026-03-09'));
        $this->assertSame('120.750', $ledger->unitsHeldOn('2026-03-10'));
    }

    public function test_entry_order_does_not_matter()
    {
        $ledger = (new UnitLedger)
            ->with('2026-03-10', '30.000', false)
            ->with('2026-01-10', '100.000', true);

        $this->assertNull($ledger->firstOverdrawnDate());
    }

    public function test_redeeming_everything_leaves_exactly_zero()
    {
        $ledger = (new UnitLedger)
            ->with('2026-01-10', '343.507', true)
            ->with('2026-02-10', '343.507', false);

        $this->assertSame('0.000', $ledger->unitsHeldOn('2026-02-10'));
        $this->assertNull($ledger->firstOverdrawnDate());
    }

    public function test_redeeming_more_than_held_is_overdrawn()
    {
        $ledger = (new UnitLedger)
            ->with('2026-01-10', '10.000', true)
            ->with('2026-02-10', '10.001', false);

        $this->assertSame('2026-02-10', $ledger->firstOverdrawnDate());
    }

    public function test_a_purchase_and_redemption_on_the_same_day_are_valid()
    {
        $ledger = (new UnitLedger)
            ->with('2026-01-10', '10.000', false)
            ->with('2026-01-10', '10.000', true);

        $this->assertNull($ledger->firstOverdrawnDate());
    }

    public function test_a_back_dated_redemption_can_overdraw_a_later_one()
    {
        // Each redemption fits on its own, but together they sell 15 of 10 units.
        $ledger = (new UnitLedger)
            ->with('2026-01-10', '10.000', true)
            ->with('2026-03-10', '8.000', false)
            ->with('2026-02-10', '7.000', false);

        $this->assertSame('2026-03-10', $ledger->firstOverdrawnDate());
    }

    public function test_a_later_purchase_does_not_cover_an_earlier_redemption()
    {
        $ledger = (new UnitLedger)
            ->with('2026-01-10', '5.000', false)
            ->with('2026-02-10', '10.000', true);

        $this->assertSame('2026-01-10', $ledger->firstOverdrawnDate());
    }

    public function test_with_returns_a_copy()
    {
        $ledger = new UnitLedger;
        $ledger->with('2026-01-10', '10.000', true);

        $this->assertSame('0.000', $ledger->unitsHeldOn('2026-12-31'));
    }

    public function test_negative_units_are_rejected()
    {
        $this->expectException(InvalidArgumentException::class);

        (new UnitLedger)->with('2026-01-10', '-1.000', true);
    }
}
