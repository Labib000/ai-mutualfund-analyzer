import {
    faArrowRight,
    faCalendar,
    faPlus,
    faWallet,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Head, Link, usePage } from '@inertiajs/react';
import AiSummaryCard from '@/components/ai/ai-summary-card';
import AllocationBar from '@/components/dashboard/allocation-bar';
import CategoryList from '@/components/dashboard/category-list';
import InsightsCard from '@/components/dashboard/insights-card';
import PortfolioSummary from '@/components/dashboard/portfolio-summary';
import ValueChart from '@/components/dashboard/value-chart';
import HoldingsTable from '@/components/portfolio/holdings-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { create, index } from '@/routes/holdings';
import type {
    Allocation,
    HistoryPoint,
    HoldingSummary,
    Insight,
    Performance,
} from '@/types/portfolio';

export default function Dashboard({
    fund_count: fundCount,
    ai_remaining: aiRemaining,
    summary,
    history,
    insights,
    allocation,
    top_holdings: topHoldings,
}: {
    fund_count: number;
    ai_remaining: number;
    summary: Performance;
    history: HistoryPoint[];
    insights: Insight[];
    allocation: Allocation;
    top_holdings: HoldingSummary[];
}) {
    const showChart = history.length > 1;
    const showAllocation = allocation.classes.length > 0;

    return (
        <>
            <Head title="Dashboard" />

            {/* One opacity fade for the page; cheap enough for low-end phones. */}
            <div className="mx-auto flex w-full max-w-7xl flex-1 animate-in flex-col gap-4 p-4 duration-300 fade-in sm:gap-6 md:p-6 lg:p-8">
                <PageHeader
                    valuedOn={summary.valued_on}
                    hasFunds={fundCount > 0}
                />

                {fundCount === 0 ? (
                    <EmptyState />
                ) : (
                    <>
                        <PortfolioSummary summary={summary} />

                        {insights.length > 0 && (
                            <InsightsCard insights={insights} />
                        )}

                        <AiSummaryCard remaining={aiRemaining} />

                        {(showChart || showAllocation) && (
                            <div className="grid gap-4 sm:gap-6 lg:grid-cols-3">
                                {showChart && (
                                    <Card
                                        className={
                                            showAllocation
                                                ? 'min-w-0 lg:col-span-2'
                                                : 'min-w-0 lg:col-span-3'
                                        }
                                    >
                                        <SectionHeader
                                            title="Value over time"
                                            description="Weekly value of your funds against the cost of the units you held."
                                        />
                                        <CardContent>
                                            <ValueChart history={history} />
                                        </CardContent>
                                    </Card>
                                )}

                                {showAllocation && (
                                    <Card
                                        className={
                                            showChart
                                                ? 'min-w-0'
                                                : 'min-w-0 lg:col-span-3'
                                        }
                                    >
                                        <SectionHeader
                                            title="Allocation"
                                            description="Share of current value by asset class and fund category."
                                        />
                                        <CardContent className="space-y-6">
                                            <AllocationBar
                                                classes={allocation.classes}
                                            />
                                            <Separator />
                                            <CategoryList
                                                categories={
                                                    allocation.categories
                                                }
                                            />
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        )}

                        <Card className="gap-4">
                            <CardHeader className="flex flex-row items-center justify-between gap-3">
                                <CardTitle>
                                    {fundCount > topHoldings.length
                                        ? `Top ${topHoldings.length} holdings`
                                        : 'Holdings'}
                                </CardTitle>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    asChild
                                    className="-mr-2"
                                >
                                    <Link href={index()}>
                                        View all {fundCount}
                                        <FontAwesomeIcon icon={faArrowRight} />
                                    </Link>
                                </Button>
                            </CardHeader>
                            <CardContent>
                                <HoldingsTable holdings={topHoldings} compact />
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    return hour < 17 ? 'Good afternoon' : 'Good evening';
}

function PageHeader({
    valuedOn,
    hasFunds,
}: {
    valuedOn: string | null;
    hasFunds: boolean;
}) {
    const { auth } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <div className="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-end sm:justify-between sm:text-left">
            <div className="flex flex-col items-center gap-2 sm:items-start">
                <h1 className="font-display text-2xl font-semibold tracking-tight sm:text-3xl">
                    {greeting()}, {firstName}
                </h1>
                {valuedOn ? (
                    <Badge
                        variant="secondary"
                        className="gap-1.5 font-normal text-muted-foreground"
                    >
                        <FontAwesomeIcon icon={faCalendar} aria-hidden="true" />
                        Valued at NAVs up to {formatDate(valuedOn)}
                    </Badge>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Your mutual fund portfolio at a glance.
                    </p>
                )}
            </div>
            {hasFunds && (
                <Button asChild className="w-full sm:w-auto">
                    <Link href={create()}>
                        <FontAwesomeIcon icon={faPlus} /> Add fund
                    </Link>
                </Button>
            )}
        </div>
    );
}

function SectionHeader({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <CardHeader className="text-center sm:text-left">
            <CardTitle>{title}</CardTitle>
            <CardDescription>{description}</CardDescription>
        </CardHeader>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center gap-5 rounded-lg border border-dashed bg-card px-6 py-16 text-center sm:py-24">
            <span className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <FontAwesomeIcon
                    icon={faWallet}
                    className="size-5"
                    aria-hidden="true"
                />
            </span>
            <div className="max-w-sm space-y-2">
                <p className="text-lg font-semibold">No funds yet</p>
                <p className="text-sm text-muted-foreground">
                    Add a fund and record your purchases or SIPs. Hisaab will
                    track its value, returns and XIRR from official AMFI NAVs.
                </p>
            </div>
            <Button asChild>
                <Link href={create()}>
                    <FontAwesomeIcon icon={faPlus} /> Add your first fund
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
