<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\Holding;
use App\Models\Scheme;
use App\Models\Sip;
use App\Models\Transaction;
use App\Portfolio\SipSchedule;
use App\Portfolio\UnitCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HoldingController extends Controller
{
    private const SEARCH_LIMIT = 20;

    public function __construct(
        private readonly UnitCalculator $calculator,
        private readonly SipSchedule $schedule,
    ) {}

    /**
     * List the user's funds.
     */
    public function index(Request $request): Response
    {
        $holdings = $request->user()->holdings()
            ->withUnitsHeld()
            ->with('scheme')
            ->get()
            ->sortBy('scheme.name')
            ->values();

        return Inertia::render('holdings/index', [
            'holdings' => $holdings->map(fn (Holding $holding) => [
                'id' => $holding->id,
                'scheme' => $this->schemeSummary($holding->scheme),
                'units' => $this->units($holding),
                'value_paise' => $this->valuePaise($holding),
            ]),
        ]);
    }

    /**
     * Search schemes to add as a fund.
     */
    public function create(Request $request): Response
    {
        $query = trim($request->string('q')->limit(100, '')->value());

        return Inertia::render('holdings/create', [
            'query' => $query,
            'results' => fn () => mb_strlen($query) < 2 ? [] : $this->search($request, $query),
        ]);
    }

    /**
     * Add a scheme to the user's portfolio.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scheme_id' => [
                'required',
                'integer',
                Rule::exists('schemes', 'id')->where('is_active', true),
                Rule::unique('holdings')->where('user_id', $request->user()->id),
            ],
        ], [
            'scheme_id.unique' => 'This fund is already in your portfolio.',
            'scheme_id.exists' => 'This fund is no longer active.',
        ]);

        $holding = $request->user()->holdings()->create(['scheme_id' => $validated['scheme_id']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fund added. Now record a purchase or a SIP.']);

        return to_route('holdings.show', $holding);
    }

    /**
     * Show one fund with its transactions and SIPs.
     */
    public function show(Holding $holding): Response
    {
        /** @var Holding $holding */
        $holding = Holding::query()->withUnitsHeld()->with('scheme')->findOrFail($holding->id);
        $today = CarbonImmutable::today();

        $transactions = $holding->transactions()
            ->orderByDesc('txn_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'txn_date' => $transaction->txn_date->toDateString(),
                'nav_date' => $transaction->nav_date->toDateString(),
                'nav' => $transaction->nav,
                'amount_paise' => $transaction->amount_paise,
                'stamp_duty_paise' => $transaction->stamp_duty_paise,
                'units' => $transaction->units,
                'units_overridden' => $transaction->units_overridden,
                'from_sip' => $transaction->sip_id !== null,
            ]);

        $sips = $holding->sips()
            ->orderBy('start_date')
            ->get()
            ->map(fn (Sip $sip) => [
                'id' => $sip->id,
                'amount_paise' => $sip->amount_paise,
                'day_of_month' => $sip->day_of_month,
                'start_date' => $sip->start_date->toDateString(),
                'end_date' => $sip->end_date?->toDateString(),
                'is_running' => $sip->isRunningOn($today),
                'next_due' => $this->nextDue($sip, $today)?->toDateString(),
                'earliest_end_date' => ($sip->generated_until ?? $sip->start_date)->toDateString(),
            ]);

        return Inertia::render('holdings/show', [
            'holding' => [
                'id' => $holding->id,
                'scheme' => $this->schemeSummary($holding->scheme),
                'units' => $this->units($holding),
                'value_paise' => $this->valuePaise($holding),
            ],
            'transactions' => $transactions,
            'sips' => $sips,
            'today' => $today->toDateString(),
        ]);
    }

    /**
     * Remove a fund with all its transactions and SIPs.
     */
    public function destroy(Holding $holding): RedirectResponse
    {
        $holding->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fund removed from your portfolio.']);

        return to_route('holdings.index');
    }

    /**
     * Active schemes whose name contains every word of the query, in any order.
     *
     * @return list<array<string, mixed>>
     */
    private function search(Request $request, string $query): array
    {
        $words = array_slice(preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 6);
        $held = $request->user()->holdings()->pluck('scheme_id')->flip();

        $schemes = Scheme::query()
            ->where('is_active', true)
            ->whereNotNull('latest_nav')
            ->where(function ($builder) use ($words) {
                foreach ($words as $word) {
                    $builder->where('name', 'like', '%'.addcslashes($word, '%_\\').'%');
                }
            })
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        return array_values($schemes->map(fn (Scheme $scheme) => [
            ...$this->schemeSummary($scheme),
            'amc' => $scheme->amc,
            'is_held' => $held->has($scheme->id),
        ])->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function schemeSummary(Scheme $scheme): array
    {
        return [
            'id' => $scheme->id,
            'name' => $scheme->name,
            'category' => $scheme->category,
            'plan' => $scheme->plan?->value,
            'latest_nav' => $scheme->latest_nav,
            'latest_nav_date' => $scheme->latest_nav_date?->toDateString(),
        ];
    }

    /**
     * @return numeric-string
     */
    private function units(Holding $holding): string
    {
        return bcadd($holding->units_held ?? '0', '0', UnitCalculator::UNITS_SCALE);
    }

    /**
     * Current value at the latest NAV, in paise.
     */
    private function valuePaise(Holding $holding): int
    {
        $units = $this->units($holding);
        $nav = $holding->scheme->latest_nav;

        if ($nav === null || bccomp($units, '0', UnitCalculator::UNITS_SCALE) <= 0) {
            return 0;
        }

        return $this->calculator->redemptionAmount($units, $nav);
    }

    /**
     * The next installment not yet recorded. It can be in the past when its NAV
     * hasn't been published yet.
     */
    private function nextDue(Sip $sip, CarbonImmutable $today): ?CarbonImmutable
    {
        if (! $sip->isRunningOn($today)) {
            return null;
        }

        $horizon = $today->max($sip->start_date)->addMonths(2);

        return $this->schedule->dueDates($sip->day_of_month, $sip->start_date, $sip->end_date, $sip->generated_until, $horizon)[0] ?? null;
    }
}
