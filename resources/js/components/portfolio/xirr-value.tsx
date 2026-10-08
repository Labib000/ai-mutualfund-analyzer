import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Performance } from '@/types/portfolio';

const hints: Record<Performance['xirr_status'], string | null> = {
    ok: null,
    short_period:
        'Annualised from less than a year of investing, so it can look higher or lower than it will over time.',
    too_recent: 'XIRR is shown once the investments span at least 30 days.',
    not_meaningful: 'Not enough transactions to work out an annual return.',
};

/**
 * XIRR with an explanation when it is missing or based on a short period.
 */
export default function XirrValue({
    performance,
    className,
}: {
    performance: Pick<Performance, 'xirr_pct' | 'xirr_status'>;
    className?: string;
}) {
    const hint = hints[performance.xirr_status];
    const value =
        performance.xirr_pct === null ? (
            '—'
        ) : (
            <span
                className={cn(
                    performance.xirr_pct > 0 &&
                        'text-emerald-700 dark:text-emerald-400',
                    performance.xirr_pct < 0 &&
                        'text-red-700 dark:text-red-400',
                )}
            >
                {formatPercent(performance.xirr_pct, { signed: true })}
                {performance.xirr_status === 'short_period' && (
                    <span aria-hidden="true">*</span>
                )}
            </span>
        );

    if (hint === null) {
        return <span className={cn('tabular-nums', className)}>{value}</span>;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span
                    tabIndex={0}
                    className={cn(
                        'cursor-help tabular-nums underline decoration-dotted underline-offset-4',
                        className,
                    )}
                >
                    {value}
                    <span className="sr-only"> ({hint})</span>
                </span>
            </TooltipTrigger>
            <TooltipContent className="max-w-64">{hint}</TooltipContent>
        </Tooltip>
    );
}
