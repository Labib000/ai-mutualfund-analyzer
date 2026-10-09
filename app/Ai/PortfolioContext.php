<?php

namespace App\Ai;

use App\Enums\AssetClass;
use App\Enums\XirrStatus;
use App\Models\Holding;
use App\Models\Sip;
use App\Models\User;
use App\Nav\SchemeStats;
use App\Portfolio\Allocation;
use App\Portfolio\Insight;
use App\Portfolio\PortfolioInsights;
use App\Portfolio\PortfolioPerformance;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * The portfolio as the AI model sees it: computed figures only. No name,
 * email, IDs or transaction history ever leave the app.
 */
class PortfolioContext
{
    public function __construct(
        private readonly PortfolioPerformance $performance,
        private readonly Allocation $allocation,
        private readonly SchemeStats $schemeStats,
        private readonly PortfolioInsights $insights,
    ) {}

    /**
     * The user's portfolio as text, or null when they hold no funds yet.
     */
    public function forUser(User $user): ?string
    {
        [
            'holdings' => $holdings,
            'performances' => $performances,
            'total' => $total,
        ] = $this->performance->forUser($user);

        if ($holdings->isEmpty()) {
            return null;
        }

        $holdings->load('sips');
        $today = CarbonImmutable::today();
        $funds = [];

        foreach ($holdings->sortByDesc(fn (Holding $h) => $performances[$h->id]->valuePaise) as $holding) {
            $performance = $performances[$holding->id];
            $firstDate = $holding->transactions->min('txn_date');
            $stats = $this->schemeStats->for($holding->scheme);

            $funds[] = [
                'name' => $holding->scheme->name,
                'category' => $holding->scheme->category,
                'asset_class' => AssetClass::fromCategory($holding->scheme->category)->label(),
                'invested_paise' => $performance->investedPaise,
                'value_paise' => $performance->valuePaise,
                'unrealised_gain_paise' => $performance->unrealisedGainPaise(),
                'absolute_return_pct' => $performance->absoluteReturnPct(),
                'realised_gain_paise' => $performance->realisedGainPaise,
                'xirr' => $performance->xirr,
                'xirr_status' => $performance->xirrStatus,
                'first_investment' => $firstDate instanceof CarbonImmutable ? $firstDate->toDateString() : null,
                'fund_stats' => $stats === null ? null : FundFacts::short($stats),
                'sips' => array_values($holding->sips
                    ->filter(fn (Sip $sip) => $sip->isRunningOn($today))
                    ->map(fn (Sip $sip) => ['amount_paise' => $sip->amount_paise, 'day' => $sip->day_of_month])
                    ->all()),
            ];
        }

        $allocation = $this->allocation->of(array_values($holdings->map(fn (Holding $h) => [
            'category' => $h->scheme->category,
            'value_paise' => $performances[$h->id]->valuePaise,
        ])->all()));

        return self::render($funds, [
            'invested_paise' => $total->investedPaise,
            'value_paise' => $total->valuePaise,
            'unrealised_gain_paise' => $total->unrealisedGainPaise(),
            'absolute_return_pct' => $total->absoluteReturnPct(),
            'realised_gain_paise' => $total->realisedGainPaise,
            'xirr' => $total->xirr,
            'xirr_status' => $total->xirrStatus,
            'valued_on' => $total->valuedOn?->toDateString(),
        ], $allocation['classes'], array_map(
            fn (Insight $insight) => "{$insight->title}. {$insight->detail}",
            $this->insights->of($holdings, $performances, $total),
        ));
    }

    /**
     * @param  list<array{name: string, category: string, asset_class: string, invested_paise: int, value_paise: int, unrealised_gain_paise: int, absolute_return_pct: ?float, realised_gain_paise: int, xirr: ?float, xirr_status: XirrStatus, first_investment: ?string, fund_stats: ?string, sips: list<array{amount_paise: int, day: int}>}>  $funds
     * @param  array{invested_paise: int, value_paise: int, unrealised_gain_paise: int, absolute_return_pct: ?float, realised_gain_paise: int, xirr: ?float, xirr_status: XirrStatus, valued_on: ?string}  $total
     * @param  list<array{label: string, pct: float}>  $classes
     * @param  list<string>  $observations  Findings of Hisaab's insight rules
     */
    public static function render(array $funds, array $total, array $classes, array $observations = []): string
    {
        $lines = [];
        $lines[] = 'Values in Indian rupees'.($total['valued_on'] ? ', at NAVs up to '.self::date($total['valued_on']) : '').'.';
        $lines[] = '';
        $lines[] = 'TOTAL: invested '.Money::formatInr($total['invested_paise'])
            .'; current value '.Money::formatInr($total['value_paise'])
            .'; unrealised gain '.self::gain($total['unrealised_gain_paise'], $total['absolute_return_pct'])
            .'; realised gain on redemptions '.Money::formatInr($total['realised_gain_paise'], signed: true)
            .'; '.self::xirr($total['xirr'], $total['xirr_status']).'.';

        if ($classes !== []) {
            $lines[] = 'ALLOCATION BY ASSET CLASS: '.implode(', ', array_map(
                fn (array $class) => $class['label'].' '.number_format($class['pct'], 1).'%',
                $classes,
            )).'.';
        }

        $lines[] = '';
        $lines[] = 'FUNDS ('.count($funds).'):';

        foreach ($funds as $index => $fund) {
            $parts = [
                ($index + 1).'. '.$fund['name'],
                'category: '.$fund['category'],
                'asset class: '.$fund['asset_class'],
            ];

            if ($fund['value_paise'] === 0 && $fund['invested_paise'] === 0 && $fund['realised_gain_paise'] !== 0) {
                $parts[] = 'fully redeemed, realised gain '.Money::formatInr($fund['realised_gain_paise'], signed: true);
            } else {
                $parts[] = 'invested '.Money::formatInr($fund['invested_paise']);
                $parts[] = 'current value '.Money::formatInr($fund['value_paise']);
                $parts[] = 'unrealised gain '.self::gain($fund['unrealised_gain_paise'], $fund['absolute_return_pct']);

                if ($fund['realised_gain_paise'] !== 0) {
                    $parts[] = 'realised gain '.Money::formatInr($fund['realised_gain_paise'], signed: true);
                }
            }

            $parts[] = self::xirr($fund['xirr'], $fund['xirr_status']);

            if ($fund['fund_stats'] !== null) {
                $parts[] = $fund['fund_stats'];
            }

            if ($fund['first_investment'] !== null) {
                $parts[] = 'investing since '.self::date($fund['first_investment']);
            }

            $parts[] = $fund['sips'] === []
                ? 'no running SIP'
                : 'running SIP: '.implode(' and ', array_map(
                    fn (array $sip) => Money::formatInr($sip['amount_paise']).' monthly on day '.$sip['day'],
                    $fund['sips'],
                ));

            $lines[] = implode(' | ', $parts);
        }

        if ($observations !== []) {
            $lines[] = '';
            $lines[] = 'OBSERVATIONS (found by Hisaab\'s rules):';

            foreach ($observations as $observation) {
                $lines[] = '- '.$observation;
            }
        }

        return implode("\n", $lines);
    }

    private static function gain(int $paise, ?float $pct): string
    {
        return Money::formatInr($paise, signed: true).($pct === null ? '' : ' ('.self::signedPercent($pct).')');
    }

    private static function xirr(?float $rate, XirrStatus $status): string
    {
        return match ($status) {
            XirrStatus::Ok => 'XIRR '.self::signedPercent(($rate ?? 0) * 100),
            XirrStatus::ShortPeriod => 'XIRR '.self::signedPercent(($rate ?? 0) * 100).' (annualised from less than a year, so not yet reliable)',
            XirrStatus::TooRecent => 'XIRR not available (investments span less than 30 days)',
            XirrStatus::NotMeaningful => 'XIRR not available',
        };
    }

    private static function signedPercent(float $pct): string
    {
        $rounded = round($pct, 2);

        return ($rounded > 0 ? '+' : ($rounded < 0 ? '−' : '')).number_format(abs($rounded), 2).'%';
    }

    private static function date(string $date): string
    {
        return CarbonImmutable::parse($date)->format('j M Y');
    }
}
