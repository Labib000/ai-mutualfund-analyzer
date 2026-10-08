import type { ReactNode } from 'react';
import Gain, { gainTone } from '@/components/portfolio/gain';
import XirrValue from '@/components/portfolio/xirr-value';
import { Card } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatPaise, formatPercent, formatSignedPaise } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Performance } from '@/types/portfolio';

/**
 * Headline figures in one panel. Phones: current value on top, then
 * invested, gain and XIRR in three columns. Tablets: 2×2. Desktop: one row.
 */
export default function PortfolioSummary({
    summary,
}: {
    summary: Performance;
}) {
    const returnPct = summary.absolute_return_pct;

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="grid grid-cols-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    label="Current value"
                    className="col-span-3 border-b sm:col-span-1 sm:border-r lg:border-b-0"
                    valueClassName="font-display text-3xl sm:text-2xl"
                >
                    {formatPaise(summary.value_paise)}
                </Stat>

                <Stat
                    label="Invested"
                    caption="Cost of units held"
                    className="border-r sm:border-r-0 sm:border-b lg:border-r lg:border-b-0"
                >
                    {formatPaise(summary.invested_paise)}
                </Stat>

                <Stat
                    label="Total gain"
                    className="border-r"
                    caption={
                        returnPct !== null && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <span
                                        tabIndex={0}
                                        className={cn(
                                            'cursor-help underline decoration-dotted underline-offset-2',
                                            gainTone(returnPct),
                                        )}
                                    >
                                        {formatPercent(returnPct, {
                                            signed: true,
                                        })}
                                        <span className="hidden sm:inline">
                                            {' '}
                                            absolute
                                        </span>
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent className="max-w-64">
                                    Absolute return: unrealised gain on the
                                    amount you still have invested.
                                    {summary.realised_gain_paise !== 0 &&
                                        ` Total gain includes ${formatSignedPaise(summary.realised_gain_paise)} realised on redemptions.`}
                                </TooltipContent>
                            </Tooltip>
                        )
                    }
                >
                    <Gain paise={summary.total_gain_paise} />
                </Stat>

                <Stat label="XIRR" caption="Annualised">
                    <XirrValue performance={summary} />
                </Stat>
            </div>
        </Card>
    );
}

function Stat({
    label,
    caption,
    children,
    className,
    valueClassName,
}: {
    label: string;
    caption?: ReactNode;
    children: ReactNode;
    className?: string;
    valueClassName?: string;
}) {
    return (
        <div
            className={cn(
                'flex min-w-0 flex-col items-center gap-1 px-2 py-4 text-center sm:items-start sm:px-6 sm:py-5 sm:text-left',
                className,
            )}
        >
            <p className="text-xs text-muted-foreground sm:text-sm">{label}</p>
            <div
                className={cn(
                    'max-w-full truncate text-base font-semibold tracking-tight tabular-nums sm:text-2xl',
                    valueClassName,
                )}
            >
                {children}
            </div>
            {caption && (
                <p className="text-xs text-muted-foreground">{caption}</p>
            )}
        </div>
    );
}
