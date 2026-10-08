<?php

namespace App\Portfolio;

use App\Models\Transaction;
use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Replays a holding's unit movements to answer "how many units did I hold on
 * this date?" and "does the balance ever go negative?".
 *
 * On the same day, purchases count before redemptions, so units bought and
 * sold on one day are valid.
 */
final class UnitLedger
{
    /** @var list<array{date: string, delta: numeric-string}> */
    private array $entries = [];

    /**
     * @param  iterable<Transaction>  $transactions
     */
    public static function fromTransactions(iterable $transactions): self
    {
        $ledger = new self;

        foreach ($transactions as $transaction) {
            $ledger = $ledger->with(
                $transaction->txn_date->toDateString(),
                $transaction->units,
                $transaction->type->addsUnits(),
            );
        }

        return $ledger;
    }

    /**
     * A copy of the ledger with one more movement.
     *
     * @param  string  $date  Y-m-d
     */
    public function with(string $date, string $units, bool $addsUnits): self
    {
        if (! Decimal::isNumeric($units) || bccomp($units, '0', UnitCalculator::UNITS_SCALE) < 0) {
            throw new InvalidArgumentException("Units must be a non-negative decimal, got [{$units}].");
        }

        $copy = clone $this;
        $copy->entries[] = ['date' => $date, 'delta' => $addsUnits ? $units : bcmul($units, '-1', UnitCalculator::UNITS_SCALE)];

        return $copy;
    }

    /**
     * Units held at the end of the given day.
     *
     * @param  string  $date  Y-m-d
     * @return numeric-string
     */
    public function unitsHeldOn(string $date): string
    {
        $balance = '0';

        foreach ($this->entries as $entry) {
            if ($entry['date'] <= $date) {
                $balance = bcadd($balance, $entry['delta'], UnitCalculator::UNITS_SCALE);
            }
        }

        return bcadd($balance, '0', UnitCalculator::UNITS_SCALE);
    }

    /**
     * The first date on which more units were redeemed than held, or null if the
     * balance never goes negative.
     */
    public function firstOverdrawnDate(): ?string
    {
        $entries = $this->entries;
        usort($entries, fn (array $a, array $b) => [$a['date'], bccomp($b['delta'], '0', 3)] <=> [$b['date'], bccomp($a['delta'], '0', 3)]);

        $balance = '0';

        foreach ($entries as $entry) {
            $balance = bcadd($balance, $entry['delta'], UnitCalculator::UNITS_SCALE);

            if (bccomp($balance, '0', UnitCalculator::UNITS_SCALE) < 0) {
                return $entry['date'];
            }
        }

        return null;
    }
}
