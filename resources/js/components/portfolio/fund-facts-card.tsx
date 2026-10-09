import { gainTone } from '@/components/portfolio/gain';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { FundStats } from '@/types/portfolio';

function Fact({
    label,
    note,
    children,
}: {
    label: string;
    note?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1 px-4 py-3 sm:px-6">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-display text-lg font-semibold tabular-nums">
                {children}
            </dd>
            {note && <p className="text-xs text-muted-foreground">{note}</p>}
        </div>
    );
}

function Return({ pct }: { pct: number | null }) {
    if (pct === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className={cn(gainTone(pct))}>
            {formatPercent(pct, { signed: true })}
        </span>
    );
}

/**
 * The scheme's own past returns and risk from its NAV history, independent of
 * when the user invested.
 */
export default function FundFactsCard({ stats }: { stats: FundStats }) {
    return (
        <Card className="gap-0">
            <CardHeader className="pb-4">
                <CardTitle>Fund facts</CardTitle>
                <CardDescription>
                    How the fund's NAV has moved, up to{' '}
                    {formatDate(stats.as_of)}. Past returns don't predict future
                    returns.
                </CardDescription>
            </CardHeader>
            <CardContent className="px-0">
                <dl className="grid grid-cols-2 divide-y border-t sm:grid-cols-3 sm:divide-y-0 lg:grid-cols-6 lg:divide-x">
                    <Fact label="1 year">
                        <Return pct={stats.return_1y_pct} />
                    </Fact>
                    <Fact label="3 years" note="a year">
                        <Return pct={stats.cagr_3y_pct} />
                    </Fact>
                    <Fact label="5 years" note="a year">
                        <Return pct={stats.cagr_5y_pct} />
                    </Fact>
                    <Fact
                        label={`Since ${formatDate(stats.since)}`}
                        note={
                            stats.since_start_annualised ? 'a year' : 'in total'
                        }
                    >
                        <Return pct={stats.since_start_pct} />
                    </Fact>
                    <Fact label="Volatility" note="spread of weekly returns">
                        {stats.volatility_pct === null
                            ? '—'
                            : formatPercent(stats.volatility_pct)}
                    </Fact>
                    <Fact
                        label="Largest fall"
                        note={
                            stats.drawdown_peak && stats.drawdown_trough
                                ? `${formatDate(stats.drawdown_peak)} – ${formatDate(stats.drawdown_trough)}`
                                : 'never fell below a peak'
                        }
                    >
                        {stats.max_drawdown_pct > 0
                            ? formatPercent(-stats.max_drawdown_pct)
                            : '0.00%'}
                    </Fact>
                </dl>
            </CardContent>
        </Card>
    );
}
