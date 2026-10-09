<?php

namespace App\Portfolio;

use App\Enums\ChangePeriod;
use App\Enums\TransactionType;
use App\Models\Holding;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Loads a user's holdings and calculates their returns.
 */
class PortfolioPerformance
{
    public function __construct(
        private readonly PerformanceCalculator $calculator,
        private readonly ValueHistory $valueHistory,
        private readonly PortfolioChange $change,
    ) {}

    /**
     * Every holding of the user with its performance, plus the portfolio total.
     *
     * @return array{holdings: Collection<int, Holding>, inputs: array<int, HoldingInput>, performances: array<int, Performance>, total: Performance}
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
            'inputs' => $inputs,
            'performances' => $performances,
            'total' => $this->calculator->forPortfolio(array_values($inputs)),
        ];
    }

    public function forHolding(Holding $holding): Performance
    {
        $holding->loadMissing(['scheme', 'transactions']);

        return $this->calculator->forHolding($this->input($holding));
    }

    /**
     * Weekly portfolio value and invested amount from the first transaction to the latest NAV.
     *
     * @param  Collection<int, Holding>  $holdings  With their schemes loaded
     * @param  array<int, HoldingInput>  $inputs  Keyed by holding ID, as returned by forUser()
     * @return list<array{date: string, value_paise: int, invested_paise: int}>
     */
    public function history(Collection $holdings, array $inputs): array
    {
        $first = $holdings->first();

        if ($first === null) {
            return [];
        }

        // One entry per user, replaced whenever its version changes, so stale
        // series never pile up in the cache table.
        $key = "portfolio-history:{$first->user_id}";
        $version = $this->version($holdings);
        $cached = Cache::get($key);

        if (is_array($cached) && ($cached['version'] ?? null) === $version) {
            return $cached['series'];
        }

        $series = $this->computeHistory($holdings, $inputs);
        Cache::put($key, ['version' => $version, 'series' => $series], now()->addDay());

        return $series;
    }

    /**
     * Changes whenever a transaction is added or deleted, or a held scheme gets a newer NAV.
     *
     * @param  Collection<int, Holding>  $holdings
     */
    public function version(Collection $holdings): string
    {
        return md5((string) json_encode($holdings->sortBy('id')->map(fn (Holding $holding) => [
            $holding->id,
            $holding->scheme->latest_nav_date?->toDateString(),
            $holding->transactions->count(),
            $holding->transactions->max('id'),
            $holding->transactions->max('updated_at')?->toIso8601String(),
        ])->values()));
    }

    /**
     * @param  Collection<int, Holding>  $holdings
     * @param  array<int, HoldingInput>  $inputs
     * @return list<array{date: string, value_paise: int, invested_paise: int}>
     */
    private function computeHistory(Collection $holdings, array $inputs): array
    {
        $firstDates = array_filter(array_map(
            fn (HoldingInput $input) => $input->movements === [] ? null : min(array_map(fn (Movement $m) => $m->date, $input->movements)),
            $inputs,
        ));

        if ($firstDates === []) {
            return [];
        }

        $navs = $this->navs($holdings, min($firstDates));

        $series = [];
        foreach ($holdings as $holding) {
            $series[] = [$inputs[$holding->id], $navs[$holding->id]];
        }

        return $this->valueHistory->series($series);
    }

    /**
     * Value change over each period up to the latest NAV, split into new money and market movement.
     *
     * @param  Collection<int, Holding>  $holdings  With schemes and transactions loaded
     * @param  array<int, HoldingInput>  $inputs  Keyed by holding ID, as returned by forUser()
     * @return array<string, PortfolioChangeResult> Keyed by period value; empty when nothing is valued yet
     */
    public function changes(Collection $holdings, array $inputs): array
    {
        $end = $holdings->map(fn (Holding $holding) => $holding->scheme->latest_nav_date)->filter()->max();

        if (! $end instanceof CarbonImmutable || $holdings->every(fn (Holding $holding) => $holding->transactions->isEmpty())) {
            return [];
        }

        $earliest = min(array_map(fn (ChangePeriod $period) => $period->startFor($end), ChangePeriod::cases()));
        $navs = $this->navs($holdings, $earliest);
        $changes = [];

        foreach (ChangePeriod::cases() as $period) {
            $from = $period->startFor($end)->toDateString();
            $to = $end->toDateString();
            $installments = $holdings->sum(fn (Holding $holding) => $holding->transactions
                ->filter(fn (Transaction $transaction) => $transaction->type === TransactionType::SipInstallment
                    && $this->rawString($transaction, 'txn_date') > $from
                    && $this->rawString($transaction, 'txn_date') <= $to)
                ->count());

            $changes[$period->value] = $this->change->over(
                $period,
                $end,
                array_values($holdings->map(fn (Holding $holding) => [$holding->scheme->name, $inputs[$holding->id], $navs[$holding->id]])->all()),
                (int) $installments,
            );
        }

        return $changes;
    }

    /**
     * Each holding's NAVs from a little before the given date, keyed by holding ID.
     *
     * @param  Collection<int, Holding>  $holdings
     * @return array<int, array<string, string>>
     */
    private function navs(Collection $holdings, CarbonImmutable $from): array
    {
        // Plain rows, not models: a decade-old fund has ~2,700 NAVs. The extra
        // days cover a start date that falls on a weekend or holiday.
        $rows = DB::table('nav_history')
            ->whereIn('scheme_id', $holdings->pluck('scheme_id')->unique()->values())
            ->where('nav_date', '>=', $from->subDays(10)->toDateString())
            ->orderBy('nav_date')
            ->get(['scheme_id', 'nav_date', 'nav']);

        $navsByScheme = [];
        foreach ($rows as $row) {
            $navsByScheme[$row->scheme_id][$row->nav_date] = $row->nav;
        }

        $navs = [];
        foreach ($holdings as $holding) {
            $navs[$holding->id] = $navsByScheme[$holding->scheme_id] ?? [];

            // The scheme's latest NAV, in case today's import hasn't reached nav_history.
            if ($holding->scheme->latest_nav !== null && $holding->scheme->latest_nav_date !== null) {
                $navs[$holding->id][$holding->scheme->latest_nav_date->toDateString()] = $holding->scheme->latest_nav;
            }
        }

        return $navs;
    }

    private function rawString(Transaction $transaction, string $attribute): string
    {
        $value = $transaction->getRawOriginal($attribute);

        return is_string($value) ? $value : '';
    }

    private function input(Holding $holding): HoldingInput
    {
        return new HoldingInput(
            array_values($holding->transactions->map(fn (Transaction $transaction) => new Movement(
                // Parsing the raw Y-m-d is ~10× faster than the date cast, which
                // matters on dashboards with thousands of SIP installments.
                CarbonImmutable::createFromFormat('!Y-m-d', $this->rawString($transaction, 'txn_date')) ?: $transaction->txn_date,
                $transaction->type->addsUnits(),
                $transaction->units,
                $transaction->amount_paise,
            ))->all()),
            $holding->scheme->latest_nav,
            $holding->scheme->latest_nav_date,
        );
    }
}
