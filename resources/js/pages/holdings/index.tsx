import { Head, Link } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus, faWallet } from '@fortawesome/free-solid-svg-icons';
import Heading from '@/components/heading';
import Gain from '@/components/portfolio/gain';
import HoldingsTable from '@/components/portfolio/holdings-table';
import StatTile from '@/components/portfolio/stat-tile';
import XirrValue from '@/components/portfolio/xirr-value';
import { Button } from '@/components/ui/button';
import { formatDate, formatPaise, formatSignedPaise } from '@/lib/format';
import { create, index } from '@/routes/holdings';
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
                                <FontAwesomeIcon icon={faPlus} /> Add fund
                            </Link>
                        </Button>
                    )}
                </div>

                {holdings.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center">
                        <FontAwesomeIcon
                            icon={faWallet}
                            className="size-10 text-muted-foreground"
                        />
                        <div className="space-y-1">
                            <p className="font-medium">No funds yet</p>
                            <p className="text-sm text-muted-foreground">
                                Search for a scheme, then record your purchases
                                and SIPs.
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={create()}>
                                <FontAwesomeIcon icon={faPlus} /> Add your first
                                fund
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

                        <HoldingsTable holdings={holdings} />

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

HoldingsIndex.layout = {
    breadcrumbs: [{ title: 'Portfolio', href: index() }],
};
