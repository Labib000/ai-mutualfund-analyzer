import { Head, Link } from '@inertiajs/react';
import { Plus, WalletCards } from 'lucide-react';
import Heading from '@/components/heading';
import Gain from '@/components/portfolio/gain';
import StatTile from '@/components/portfolio/stat-tile';
import XirrValue from '@/components/portfolio/xirr-value';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatPaise, formatSignedPaise } from '@/lib/format';
import { create, index, show } from '@/routes/holdings';
import type { HoldingSummary, Performance } from '@/types/portfolio';

export default function HoldingsIndex({
    holdings,
    summary,
}: {
    holdings: HoldingSummary[];
    summary: Performance;
}) {
    return (
        <>
            <Head title="Portfolio" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Portfolio"
                        description={
                            holdings.length > 0
                                ? `${holdings.length} fund${holdings.length === 1 ? '' : 's'}${summary.valued_on ? ` · valued at NAVs up to ${formatDate(summary.valued_on)}` : ''}`
                                : 'Add the mutual funds you hold to start tracking them.'
                        }
                    />
                    {holdings.length > 0 && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Add fund
                            </Link>
                        </Button>
                    )}
                </div>

                {holdings.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center">
                        <WalletCards className="size-10 text-muted-foreground" />
                        <div className="space-y-1">
                            <p className="font-medium">No funds yet</p>
                            <p className="text-sm text-muted-foreground">
                                Search for a scheme, then record your purchases
                                and SIPs.
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Add your first fund
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatTile label="Invested">
                                {formatPaise(summary.invested_paise)}
                            </StatTile>
                            <StatTile label="Current value">
                                {formatPaise(summary.value_paise)}
                            </StatTile>
                            <StatTile
                                label="Total gain"
                                note={
                                    summary.realised_gain_paise !== 0 &&
                                    `includes ${formatSignedPaise(summary.realised_gain_paise)} realised on redemptions`
                                }
                            >
                                <Gain paise={summary.total_gain_paise} />
                            </StatTile>
                            <StatTile label="XIRR">
                                <XirrValue performance={summary} />
                            </StatTile>
                        </div>

                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Fund
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Invested
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Current value
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Gain
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            XIRR
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {holdings.map((holding) => (
                                        <HoldingRow
                                            key={holding.id}
                                            holding={holding}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Invested is the cost of the units you still hold,
                            oldest units sold first. Gain is current value minus
                            invested. * XIRR annualised from less than a year.
                        </p>
                    </>
                )}
            </div>
        </>
    );
}

function HoldingRow({ holding }: { holding: HoldingSummary }) {
    const { performance } = holding;

    return (
        <tr className="border-t">
            <td className="px-4 py-3">
                <Link
                    href={show(holding.id)}
                    className="font-medium hover:underline"
                >
                    {holding.scheme.name}
                </Link>
                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <span>{holding.scheme.category}</span>
                    {holding.scheme.plan && (
                        <Badge variant="outline" className="capitalize">
                            {holding.scheme.plan}
                        </Badge>
                    )}
                </div>
            </td>
            <td className="px-4 py-3 text-right tabular-nums">
                {formatPaise(performance.invested_paise)}
            </td>
            <td className="px-4 py-3 text-right font-medium tabular-nums">
                {formatPaise(performance.value_paise)}
            </td>
            <td className="px-4 py-3 text-right whitespace-nowrap">
                <Gain
                    paise={performance.unrealised_gain_paise}
                    pct={performance.absolute_return_pct}
                />
                {performance.realised_gain_paise !== 0 && (
                    <div className="text-xs text-muted-foreground">
                        realised{' '}
                        {formatSignedPaise(performance.realised_gain_paise)}
                    </div>
                )}
            </td>
            <td className="px-4 py-3 text-right">
                <XirrValue performance={performance} />
            </td>
        </tr>
    );
}

HoldingsIndex.layout = {
    breadcrumbs: [{ title: 'Portfolio', href: index() }],
};
