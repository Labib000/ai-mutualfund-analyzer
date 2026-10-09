<?php

namespace App\Portfolio;

use App\Models\Holding;
use App\Models\Sip;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the insight rules' input from a user's holdings.
 */
class PortfolioInsights
{
    public function __construct(private readonly Insights $insights) {}

    /**
     * @param  Collection<int, Holding>  $holdings  With schemes and transactions loaded, as from PortfolioPerformance::forUser()
     * @param  array<int, Performance>  $performances  Keyed by holding ID
     * @return list<Insight>
     */
    public function of(Collection $holdings, array $performances, Performance $total): array
    {
        $holdings->loadMissing('sips');
        $today = CarbonImmutable::today();

        $funds = array_values($holdings->map(function (Holding $holding) use ($performances, $today) {
            $last = $holding->transactions->max('txn_date');

            return new InsightFund(
                name: $holding->scheme->name,
                amc: $holding->scheme->amc,
                category: $holding->scheme->category,
                plan: $holding->scheme->plan,
                investedPaise: $performances[$holding->id]->investedPaise,
                valuePaise: $performances[$holding->id]->valuePaise,
                lastTransaction: $last instanceof CarbonImmutable ? $last : null,
                hasRunningSip: $holding->sips->contains(fn (Sip $sip) => $sip->isRunningOn($today)),
            );
        })->all());

        return $this->insights->of($funds, $total->xirrStatus, $today);
    }
}
