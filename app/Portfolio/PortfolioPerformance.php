<?php

namespace App\Portfolio;

use App\Models\Holding;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Loads a user's holdings and calculates their returns.
 */
class PortfolioPerformance
{
    public function __construct(private readonly PerformanceCalculator $calculator) {}

    /**
     * Every holding of the user with its performance, plus the portfolio total.
     *
     * @return array{holdings: Collection<int, Holding>, performances: array<int, Performance>, total: Performance}
     */
    public function forUser(User $user): array
    {
        $holdings = $user->holdings()->with(['scheme', 'transactions'])->get();

        $inputs = [];
        $performances = [];

        foreach ($holdings as $holding) {
            $inputs[$holding->id] = $this->input($holding);
            $performances[$holding->id] = $this->calculator->forHolding($inputs[$holding->id]);
        }

        return [
            'holdings' => $holdings,
            'performances' => $performances,
            'total' => $this->calculator->forPortfolio(array_values($inputs)),
        ];
    }

    public function forHolding(Holding $holding): Performance
    {
        $holding->loadMissing(['scheme', 'transactions']);

        return $this->calculator->forHolding($this->input($holding));
    }

    private function input(Holding $holding): HoldingInput
    {
        return new HoldingInput(
            array_values($holding->transactions->map(fn (Transaction $transaction) => new Movement(
                $transaction->txn_date,
                $transaction->type->addsUnits(),
                $transaction->units,
                $transaction->amount_paise,
            ))->all()),
            $holding->scheme->latest_nav,
            $holding->scheme->latest_nav_date,
        );
    }
}
