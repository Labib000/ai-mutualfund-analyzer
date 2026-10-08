import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Plus, WalletCards } from 'lucide-react';
import AiSummaryCard from '@/components/ai/ai-summary-card';
import AllocationBar from '@/components/dashboard/allocation-bar';
import CategoryList from '@/components/dashboard/category-list';
import ValueChart from '@/components/dashboard/value-chart';
import Heading from '@/components/heading';
import Gain from '@/components/portfolio/gain';
import HoldingsTable from '@/components/portfolio/holdings-table';
import StatTile from '@/components/portfolio/stat-tile';
import XirrValue from '@/components/portfolio/xirr-value';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate, formatPaise, formatSignedPaise } from '@/lib/format';
import { dashboard } from '@/routes';
import { create, index } from '@/routes/holdings';
import type {
    Allocation,
    HistoryPoint,
    HoldingSummary,
    Performance,
} from '@/types/portfolio';

export default function Dashboard({
    fund_count: fundCount,
    ai_remaining: aiRemaining,
    summary,
    history,
    allocation,
    top_holdings: topHoldings,
}: {
    fund_count: number;
    ai_remaining: number;
    summary: Performance;
    history: HistoryPoint[];
    allocation: Allocation;
    top_holdings: HoldingSummary[];
}) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Dashboard"
                    description={
                        summary.valued_on
                            ? `Your portfolio at NAVs up to ${formatDate(summary.valued_on)}`
                            : 'Your mutual fund portfolio at a glance.'
                    }
                />

                {fundCount === 0 ? (
                    <EmptyState />
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

                        <AiSummaryCard remaining={aiRemaining} />

                        {history.length > 1 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Value over time</CardTitle>
                                    <CardDescription>
                                        Weekly value of your funds against the
                                        cost of the units you held.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ValueChart history={history} />
                                </CardContent>
                            </Card>
                        )}

                        {allocation.classes.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Allocation</CardTitle>
                                    <CardDescription>
                                        Share of current value by asset class
                                        and fund category.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-8 lg:grid-cols-2">
                                    <AllocationBar
                                        classes={allocation.classes}
                                    />
                                    <CategoryList
                                        categories={allocation.categories}
                                    />
                                </CardContent>
                            </Card>
                        )}

                        <div className="space-y-3">
                            <div className="flex items-center justify-between gap-4">
                                <h2 className="text-base font-medium">
                                    {fundCount > topHoldings.length
                                        ? `Top ${topHoldings.length} holdings`
                                        : 'Holdings'}
                                </h2>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link href={index()}>
                                        View all {fundCount} <ArrowRight />
                                    </Link>
                                </Button>
                            </div>
                            <HoldingsTable holdings={topHoldings} compact />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center">
            <WalletCards className="size-10 text-muted-foreground" />
            <div className="space-y-1">
                <p className="font-medium">Your dashboard is empty</p>
                <p className="text-sm text-muted-foreground">
                    Add a fund and record your purchases or SIPs to see your
                    returns here.
                </p>
            </div>
            <Button asChild>
                <Link href={create()}>
                    <Plus /> Add your first fund
                </Link>
            </Button>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
