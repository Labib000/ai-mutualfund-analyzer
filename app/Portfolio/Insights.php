<?php

namespace App\Portfolio;

use App\Enums\AssetClass;
use App\Enums\SchemePlan;
use App\Enums\XirrStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Factual observations about a portfolio, found by fixed rules so they can be
 * shown without AI and tested exactly. They describe; they never advise.
 */
final class Insights
{
    /** A single fund above this share of value is called out. */
    public const FUND_SHARE = 0.40;

    /** A single fund house above this share of value is called out. */
    public const AMC_SHARE = 0.50;

    /** A fund with no SIP and no transaction for this long counts as idle. */
    public const IDLE_MONTHS = 12;

    /**
     * @param  list<InsightFund>  $funds
     * @return list<Insight> Attention first, then information
     */
    public function of(array $funds, XirrStatus $portfolioXirr, CarbonImmutable $today): array
    {
        $held = array_values(array_filter($funds, fn (InsightFund $fund) => $fund->valuePaise > 0));
        $total = array_sum(array_map(fn (InsightFund $fund) => $fund->valuePaise, $held));

        if ($total === 0) {
            return [];
        }

        $insights = array_filter([
            $this->losses($held),
            $this->regularPlans($held),
            $this->fundConcentration($held, $total),
            $this->amcConcentration($held, $total),
            $this->singleAssetClass($held),
            ...$this->sameCategory($held),
            $this->idle($held, $today),
            $this->shortHistory($portfolioXirr),
        ]);

        usort($insights, fn (Insight $a, Insight $b) => ($a->tone === 'attention' ? 0 : 1) <=> ($b->tone === 'attention' ? 0 : 1));

        return $insights;
    }

    /**
     * @param  list<InsightFund>  $held
     */
    private function losses(array $held): ?Insight
    {
        $losing = array_values(array_filter($held, fn (InsightFund $fund) => $fund->valuePaise < $fund->investedPaise));

        if ($losing === []) {
            return null;
        }

        if (count($losing) === 1) {
            $fund = $losing[0];
            $loss = $fund->investedPaise - $fund->valuePaise;

            return new Insight(
                'loss',
                'attention',
                "{$fund->shortName()} is below its cost",
                'Its current value is '.Money::formatInr($loss).' ('.self::pct($loss / $fund->investedPaise).') less than the cost of the units held.',
            );
        }

        return new Insight(
            'loss',
            'attention',
            count($losing).' funds are below their cost',
            'Current value is less than the cost of the units held in '.self::names($losing).'.',
        );
    }

    /**
     * @param  list<InsightFund>  $held
     */
    private function regularPlans(array $held): ?Insight
    {
        $regular = array_values(array_filter($held, fn (InsightFund $fund) => $fund->plan === SchemePlan::Regular));

        if ($regular === []) {
            return null;
        }

        return new Insight(
            'regular_plan',
            'info',
            count($regular) === 1 ? "{$regular[0]->shortName()} is a Regular plan" : count($regular).' funds are Regular plans',
            'Regular plans include a distributor commission, so their expense ratio is higher than the Direct plan of the same scheme.',
        );
    }

    /**
     * @param  list<InsightFund>  $held
     */
    private function fundConcentration(array $held, int $total): ?Insight
    {
        if (count($held) < 2) {
            return null;
        }

        usort($held, fn (InsightFund $a, InsightFund $b) => $b->valuePaise <=> $a->valuePaise);
        $share = $held[0]->valuePaise / $total;

        if ($share <= self::FUND_SHARE) {
            return null;
        }

        return new Insight(
            'fund_concentration',
            'info',
            "{$held[0]->shortName()} is ".self::pct($share).' of your portfolio',
            'With this much in one fund, its ups and downs largely decide how your whole portfolio moves.',
        );
    }

    /**
     * @param  list<InsightFund>  $held
     */
    private function amcConcentration(array $held, int $total): ?Insight
    {
        if (count($held) < 2) {
            return null;
        }

        $byAmc = [];
        foreach ($held as $fund) {
            $byAmc[$fund->amc] = ($byAmc[$fund->amc] ?? 0) + $fund->valuePaise;
        }

        arsort($byAmc);
        $amc = (string) array_key_first($byAmc);
        $share = $byAmc[$amc] / $total;

        if ($share <= self::AMC_SHARE) {
            return null;
        }

        return new Insight(
            'amc_concentration',
            'info',
            count($byAmc) === 1 ? "All your funds are from {$amc}" : self::pct($share)." of your money is with {$amc}",
            'Funds from one fund house are run under the same investment process and policies.',
        );
    }

    /**
     * @param  list<InsightFund>  $held
     */
    private function singleAssetClass(array $held): ?Insight
    {
        $classes = array_unique(array_map(fn (InsightFund $fund) => AssetClass::fromCategory($fund->category), $held), SORT_REGULAR);

        if (count($classes) !== 1) {
            return null;
        }

        $class = $classes[array_key_first($classes)];

        return new Insight(
            'single_asset_class',
            'info',
            "All your money is in {$class->label()} funds",
            match ($class) {
                AssetClass::Equity => 'Equity funds follow the stock market, so their value can rise and fall sharply over short periods.',
                AssetClass::Debt => 'Debt funds lend to governments and companies; their value moves with interest rates and credit quality.',
                default => "Your portfolio's ups and downs follow how {$class->label()} funds behave.",
            },
        );
    }

    /**
     * @param  list<InsightFund>  $held
     * @return list<Insight>
     */
    private function sameCategory(array $held): array
    {
        $byCategory = [];
        foreach ($held as $fund) {
            $byCategory[$fund->category][] = $fund;
        }

        $insights = [];
        foreach ($byCategory as $category => $funds) {
            if (count($funds) < 2) {
                continue;
            }

            $label = explode(' - ', (string) $category, 2)[1] ?? (string) $category;
            $insights[] = new Insight(
                'same_category',
                'info',
                count($funds)." funds in {$label}",
                self::names($funds).' are in the same category, so they often invest in many of the same securities.',
            );
        }

        return $insights;
    }

    /**
     * @param  list<InsightFund>  $held
     */
    private function idle(array $held, CarbonImmutable $today): ?Insight
    {
        $cutoff = $today->subMonths(self::IDLE_MONTHS);
        $idle = array_values(array_filter($held, fn (InsightFund $fund) => ! $fund->hasRunningSip
            && $fund->lastTransaction !== null
            && $fund->lastTransaction->lessThan($cutoff)));

        if ($idle === []) {
            return null;
        }

        return new Insight(
            'idle',
            'info',
            count($idle) === 1 ? "No new money in {$idle[0]->shortName()} for over a year" : count($idle).' funds had no new money for over a year',
            count($idle) === 1
                ? 'It has no running SIP and no transaction in the last '.self::IDLE_MONTHS.' months.'
                : self::names($idle).' have no running SIP and no transaction in the last '.self::IDLE_MONTHS.' months.',
        );
    }

    private function shortHistory(XirrStatus $status): ?Insight
    {
        return match ($status) {
            XirrStatus::ShortPeriod => new Insight(
                'short_history',
                'info',
                'Your XIRR covers less than a year',
                'An annualised return from a short period can swing a lot; it settles as your investments age.',
            ),
            XirrStatus::TooRecent => new Insight(
                'short_history',
                'info',
                'XIRR appears after 30 days',
                'Your investments span less than 30 days, too short for a meaningful annual return.',
            ),
            default => null,
        };
    }

    /**
     * @param  list<InsightFund>  $funds
     */
    private static function names(array $funds): string
    {
        $names = array_map(fn (InsightFund $fund) => $fund->shortName(), $funds);
        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names).' and '.$last;
    }

    private static function pct(float $fraction): string
    {
        return number_format($fraction * 100, 1).'%';
    }
}
