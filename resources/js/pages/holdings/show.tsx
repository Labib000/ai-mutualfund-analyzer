import { Head, usePage } from '@inertiajs/react';
import { Pencil, Repeat, Trash2 } from 'lucide-react';
import HoldingController from '@/actions/App/Http/Controllers/Portfolio/HoldingController';
import SipController from '@/actions/App/Http/Controllers/Portfolio/SipController';
import TransactionController from '@/actions/App/Http/Controllers/Portfolio/TransactionController';
import ExplainFundDialog from '@/components/ai/explain-fund-dialog';
import AlertError from '@/components/alert-error';
import ConfirmDialog from '@/components/portfolio/confirm-dialog';
import Gain from '@/components/portfolio/gain';
import PurchaseDialog from '@/components/portfolio/purchase-dialog';
import RedemptionDialog from '@/components/portfolio/redemption-dialog';
import SipDialog from '@/components/portfolio/sip-dialog';
import StatTile from '@/components/portfolio/stat-tile';
import XirrValue from '@/components/portfolio/xirr-value';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    formatDate,
    formatNav,
    formatPaise,
    formatSignedPaise,
    formatUnits,
    ordinal,
} from '@/lib/format';
import { index } from '@/routes/holdings';
import type {
    HoldingSummary,
    PortfolioSip,
    PortfolioTransaction,
    TransactionType,
} from '@/types/portfolio';

const typeLabels: Record<TransactionType, string> = {
    purchase: 'Purchase',
    sip_installment: 'SIP',
    redemption: 'Redemption',
};

export default function HoldingShow({
    holding,
    transactions,
    sips,
    today,
    ai_remaining: aiRemaining,
}: {
    holding: HoldingSummary;
    transactions: PortfolioTransaction[];
    sips: PortfolioSip[];
    today: string;
    ai_remaining: number;
}) {
    const { errors } = usePage().props;
    const actionErrors = [errors.transaction, errors.sip].flatMap((error) =>
        error ? [String(error)] : [],
    );
    const { performance } = holding;
    const unitsHeld = performance.units_held ?? '0';
    const hasUnits = Number(unitsHeld) > 0;

    return (
        <>
            <Head title={holding.scheme.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {holding.scheme.name}
                        </h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>{holding.scheme.category}</span>
                            {holding.scheme.plan && (
                                <Badge variant="outline" className="capitalize">
                                    {holding.scheme.plan}
                                </Badge>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <ExplainFundDialog
                            holdingId={holding.id}
                            schemeName={holding.scheme.name}
                            remaining={aiRemaining}
                        />
                        <PurchaseDialog holdingId={holding.id} today={today} />
                        <RedemptionDialog
                            holdingId={holding.id}
                            today={today}
                            disabled={!hasUnits}
                        />
                        <SipDialog
                            holdingId={holding.id}
                            today={today}
                            trigger={
                                <Button variant="outline">
                                    <Repeat /> Add SIP
                                </Button>
                            }
                        />
                    </div>
                </div>

                {actionErrors.length > 0 && (
                    <AlertError
                        errors={actionErrors}
                        title="That change wasn't made."
                    />
                )}

                <div className="space-y-2">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatTile label="Invested">
                            {formatPaise(performance.invested_paise)}
                        </StatTile>
                        <StatTile label="Current value">
                            {formatPaise(performance.value_paise)}
                        </StatTile>
                        <StatTile
                            label="Gain"
                            note={
                                performance.realised_gain_paise !== 0 &&
                                `plus ${formatSignedPaise(performance.realised_gain_paise)} realised on redemptions`
                            }
                        >
                            <Gain
                                paise={performance.unrealised_gain_paise}
                                pct={performance.absolute_return_pct}
                            />
                        </StatTile>
                        <StatTile label="XIRR">
                            <XirrValue performance={performance} />
                        </StatTile>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {formatUnits(unitsHeld)} units
                        {holding.scheme.latest_nav &&
                            ` · NAV ${formatNav(holding.scheme.latest_nav)}`}
                        {holding.scheme.latest_nav_date &&
                            ` on ${formatDate(holding.scheme.latest_nav_date)}`}
                    </p>
                </div>

                {sips.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>SIPs</CardTitle>
                            <CardDescription>
                                Installments are recorded automatically once
                                each day's NAV is published.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {sips.map((sip) => (
                                <SipRow
                                    key={sip.id}
                                    sip={sip}
                                    holdingId={holding.id}
                                    today={today}
                                />
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Transactions</CardTitle>
                        {transactions.length === 0 && (
                            <CardDescription>
                                No transactions yet. Add a purchase or a SIP to
                                get started.
                            </CardDescription>
                        )}
                    </CardHeader>
                    {transactions.length > 0 && (
                        <CardContent className="overflow-x-auto px-0">
                            <table className="w-full text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-6 py-2 font-medium">
                                            Date
                                        </th>
                                        <th className="px-6 py-2 font-medium">
                                            Type
                                        </th>
                                        <th className="px-6 py-2 text-right font-medium">
                                            Amount
                                        </th>
                                        <th className="px-6 py-2 text-right font-medium">
                                            NAV
                                        </th>
                                        <th className="px-6 py-2 text-right font-medium">
                                            Units
                                        </th>
                                        <th className="px-6 py-2">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transactions.map((transaction) => (
                                        <TransactionRow
                                            key={transaction.id}
                                            transaction={transaction}
                                            holdingId={holding.id}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    )}
                </Card>

                <div className="flex justify-end">
                    <ConfirmDialog
                        form={HoldingController.destroy.form(holding.id)}
                        title="Remove this fund?"
                        description="This deletes the fund from your portfolio along with all its transactions and SIPs. This cannot be undone."
                        confirmLabel="Remove fund"
                        trigger={
                            <Button
                                variant="ghost"
                                className="text-destructive"
                            >
                                <Trash2 /> Remove fund
                            </Button>
                        }
                    />
                </div>
            </div>
        </>
    );
}

function SipRow({
    sip,
    holdingId,
    today,
}: {
    sip: PortfolioSip;
    holdingId: number;
    today: string;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
            <div className="space-y-1">
                <p className="font-medium">
                    {formatPaise(sip.amount_paise)} on the{' '}
                    {ordinal(sip.day_of_month)} of every month
                </p>
                <p className="text-xs text-muted-foreground">
                    Since {formatDate(sip.start_date)}
                    {sip.end_date && ` · ends ${formatDate(sip.end_date)}`}
                    {sip.next_due &&
                        ` · next installment ${formatDate(sip.next_due)}`}
                </p>
            </div>

            <div className="flex items-center gap-2">
                {sip.is_running ? (
                    <Badge variant="secondary">Running</Badge>
                ) : (
                    <Badge variant="outline">Stopped</Badge>
                )}
                <SipDialog
                    holdingId={holdingId}
                    today={today}
                    sip={sip}
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Edit SIP"
                        >
                            <Pencil />
                        </Button>
                    }
                />
                {sip.is_running && (
                    <ConfirmDialog
                        form={SipController.stop.form([holdingId, sip.id])}
                        title="Stop this SIP?"
                        description="Installments up to today are kept. No new installments will be recorded."
                        confirmLabel="Stop SIP"
                        destructive={false}
                        trigger={
                            <Button variant="outline" size="sm">
                                Stop
                            </Button>
                        }
                    />
                )}
                <ConfirmDialog
                    form={SipController.destroy.form([holdingId, sip.id])}
                    title="Delete this SIP?"
                    description="This deletes the SIP and every installment it recorded. To keep past installments, stop the SIP instead."
                    confirmLabel="Delete SIP"
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Delete SIP"
                        >
                            <Trash2 />
                        </Button>
                    }
                />
            </div>
        </div>
    );
}

function TransactionRow({
    transaction,
    holdingId,
}: {
    transaction: PortfolioTransaction;
    holdingId: number;
}) {
    const navDiffers = transaction.nav_date !== transaction.txn_date;

    return (
        <tr className="border-t">
            <td className="px-6 py-3 whitespace-nowrap">
                {formatDate(transaction.txn_date)}
            </td>
            <td className="px-6 py-3">
                <Badge
                    variant={
                        transaction.type === 'redemption'
                            ? 'outline'
                            : 'secondary'
                    }
                >
                    {typeLabels[transaction.type]}
                </Badge>
            </td>
            <td className="px-6 py-3 text-right whitespace-nowrap tabular-nums">
                {formatPaise(transaction.amount_paise)}
                {transaction.stamp_duty_paise > 0 && (
                    <div className="text-xs text-muted-foreground">
                        stamp duty {formatPaise(transaction.stamp_duty_paise)}
                    </div>
                )}
            </td>
            <td className="px-6 py-3 text-right whitespace-nowrap tabular-nums">
                {formatNav(transaction.nav)}
                {navDiffers && (
                    <div className="text-xs text-muted-foreground">
                        NAV of {formatDate(transaction.nav_date)}
                    </div>
                )}
            </td>
            <td className="px-6 py-3 text-right whitespace-nowrap tabular-nums">
                {transaction.type === 'redemption' && '−'}
                {formatUnits(transaction.units)}
                {transaction.units_overridden && (
                    <div className="text-xs text-muted-foreground">
                        from statement
                    </div>
                )}
            </td>
            <td className="px-6 py-3 text-right">
                <ConfirmDialog
                    form={TransactionController.destroy.form([
                        holdingId,
                        transaction.id,
                    ])}
                    title="Delete this transaction?"
                    description={
                        transaction.from_sip
                            ? 'This removes a single SIP installment. It will not be recorded again.'
                            : 'To correct a transaction, delete it and add it again.'
                    }
                    confirmLabel="Delete"
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Delete transaction"
                        >
                            <Trash2 />
                        </Button>
                    }
                />
            </td>
        </tr>
    );
}

HoldingShow.layout = {
    breadcrumbs: [{ title: 'Portfolio', href: index() }],
};
