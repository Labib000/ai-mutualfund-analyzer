<?php

namespace App\Ai;

use App\Portfolio\ChangeFund;
use App\Portfolio\PortfolioChangeResult;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * A portfolio's change over a period as the AI model sees it.
 */
final class ChangeText
{
    public static function render(PortfolioChangeResult $change): string
    {
        $lines = [
            'CHANGE OVER THE '.strtoupper($change->period->label()).' ('.self::date($change->from).' to '.self::date($change->to).'): '
                .'value '.Money::formatInr($change->startPaise()).' → '.Money::formatInr($change->endPaise())
                .'; new money in (purchases and SIPs minus redemptions) '.Money::formatInr($change->netFlowPaise(), signed: true)
                .'; market movement '.self::movement($change->marketPaise(), $change->marketPct())
                .'; SIP installments recorded: '.$change->sipInstallments.'.',
        ];

        foreach ($change->funds as $fund) {
            $lines[] = '- '.$fund->name.': '.self::fund($fund);
        }

        return implode("\n", $lines);
    }

    private static function fund(ChangeFund $fund): string
    {
        $parts = ['market movement '.self::movement($fund->marketPaise(), $fund->marketPct())];

        if ($fund->netFlowPaise !== 0) {
            $parts[] = 'new money '.Money::formatInr($fund->netFlowPaise, signed: true);
        }

        if ($fund->startPaise === 0) {
            $parts[] = 'first bought in this period';
        } elseif ($fund->endPaise === 0) {
            $parts[] = 'fully redeemed in this period';
        }

        return implode('; ', $parts);
    }

    private static function movement(int $paise, ?float $pct): string
    {
        if ($pct === null) {
            return Money::formatInr($paise, signed: true);
        }

        $rounded = round($pct, 2);
        $sign = $rounded > 0 ? '+' : ($rounded < 0 ? '−' : '');

        return Money::formatInr($paise, signed: true).' ('.$sign.number_format(abs($rounded), 2).'%)';
    }

    private static function date(string $date): string
    {
        return CarbonImmutable::parse($date)->format('j M Y');
    }
}
