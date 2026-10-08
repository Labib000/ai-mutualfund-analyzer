import { Link } from '@inertiajs/react';
import Gain from '@/components/portfolio/gain';
import XirrValue from '@/components/portfolio/xirr-value';
import { Badge } from '@/components/ui/badge';
import { formatPaise, formatSignedPaise } from '@/lib/format';
import { show } from '@/routes/holdings';
import type { HoldingSummary } from '@/types/portfolio';

/**
 * Funds with invested amount, value, gain and XIRR. `compact` drops the invested column.
 */
export default function HoldingsTable({
    holdings,
    compact = false,
}: {
    holdings: HoldingSummary[];
    compact?: boolean;
}) {
    return (
        <>
            {/* Phones: one card per fund, since five columns don't fit. */}
            <ul className="divide-y rounded-xl border sm:hidden">
                {holdings.map((holding) => (
                    <HoldingCard key={holding.id} holding={holding} />
                ))}
            </ul>

            <div className="hidden overflow-x-auto rounded-xl border sm:block">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3 font-medium">Fund</th>
                            {!compact && (
                                <th className="px-4 py-3 text-right font-medium">
                                    Invested
                                </th>
                            )}
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
                                compact={compact}
                            />
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}

function HoldingCard({ holding }: { holding: HoldingSummary }) {
    const { performance } = holding;

    return (
        <li className="space-y-3 p-4">
            <div className="space-y-1">
                <Link
                    href={show(holding.id)}
                    className="font-medium hover:underline"
                >
                    {holding.scheme.name}
                </Link>
                <p className="text-xs text-muted-foreground">
                    {holding.scheme.category}
                </p>
            </div>
            <dl className="grid grid-cols-3 gap-2 text-sm">
                <div>
                    <dt className="text-xs text-muted-foreground">Value</dt>
                    <dd className="font-medium tabular-nums">
                        {formatPaise(performance.value_paise)}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">Gain</dt>
                    <dd>
                        <Gain paise={performance.unrealised_gain_paise} />
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">XIRR</dt>
                    <dd>
                        <XirrValue performance={performance} />
                    </dd>
                </div>
            </dl>
        </li>
    );
}

function HoldingRow({
    holding,
    compact,
}: {
    holding: HoldingSummary;
    compact: boolean;
}) {
    const { performance } = holding;

    return (
        <tr className="border-t transition-colors hover:bg-muted/40">
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
            {!compact && (
                <td className="px-4 py-3 text-right tabular-nums">
                    {formatPaise(performance.invested_paise)}
                </td>
            )}
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
