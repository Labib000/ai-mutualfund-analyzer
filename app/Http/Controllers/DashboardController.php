<?php

namespace App\Http\Controllers;

use App\Http\Presenters\SchemePresenter;
use App\Models\Holding;
use App\Portfolio\Allocation;
use App\Portfolio\PortfolioPerformance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const TOP_HOLDINGS = 5;

    public function __construct(
        private readonly PortfolioPerformance $performance,
        private readonly Allocation $allocation,
    ) {}

    /**
     * Portfolio overview: totals, value over time, allocation and top holdings.
     */
    public function index(Request $request): Response
    {
        [
            'holdings' => $holdings,
            'inputs' => $inputs,
            'performances' => $performances,
            'total' => $total,
        ] = $this->performance->forUser($request->user());

        $byValue = $holdings
            ->sortByDesc(fn (Holding $holding) => $performances[$holding->id]->valuePaise)
            ->values();

        return Inertia::render('dashboard', [
            'fund_count' => $holdings->count(),
            'summary' => $total->toArray(),
            'history' => $this->performance->history($holdings, $inputs),
            'allocation' => $this->allocation->of(array_values($holdings->map(fn (Holding $holding) => [
                'category' => $holding->scheme->category,
                'value_paise' => $performances[$holding->id]->valuePaise,
            ])->all())),
            'top_holdings' => $byValue->take(self::TOP_HOLDINGS)->map(fn (Holding $holding) => [
                'id' => $holding->id,
                'scheme' => SchemePresenter::summary($holding->scheme),
                'performance' => $performances[$holding->id]->toArray(),
            ])->values(),
        ]);
    }
}
