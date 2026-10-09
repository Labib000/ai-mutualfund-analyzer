import { faWandMagicSparkles } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { useState } from 'react';
import AiAnswer from '@/components/ai/ai-answer';
import RemainingNote from '@/components/ai/remaining-note';
import { useAiRequest } from '@/components/ai/use-ai-request';
import Gain from '@/components/portfolio/gain';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDate, formatSignedPaise } from '@/lib/format';
import { digest } from '@/routes/ai';
import type {
    ChangeFund,
    ChangePeriod,
    PortfolioChange,
} from '@/types/portfolio';

const PERIODS: { key: ChangePeriod; label: string }[] = [
    { key: '7d', label: '7D' },
    { key: '30d', label: '30D' },
    { key: 'month', label: 'This month' },
];

/**
 * How the portfolio's value moved over a period, split into new money and
 * market movement. The figures are computed on the server; only "Explain
 * this" calls the AI.
 */
export default function ChangeCard({
    changes,
    remaining,
}: {
    changes: Partial<Record<ChangePeriod, PortfolioChange>>;
    remaining: number;
}) {
    const [period, setPeriod] = useState<ChangePeriod>('7d');
    const change = changes[period];

    if (!change) {
        return null;
    }

    return (
        <Card className="gap-0">
            <CardHeader className="flex flex-col items-center gap-3 pb-4 text-center sm:flex-row sm:flex-wrap sm:justify-between sm:text-left">
                <div className="space-y-1">
                    <CardTitle>What changed</CardTitle>
                    <CardDescription>
                        {formatDate(change.from)} to {formatDate(change.to)}
                        {change.sip_installments > 0 &&
                            ` · ${change.sip_installments} SIP installment${change.sip_installments === 1 ? '' : 's'} recorded`}
                    </CardDescription>
                </div>
                <ToggleGroup
                    type="single"
                    size="sm"
                    variant="outline"
                    value={period}
                    onValueChange={(next) =>
                        next && setPeriod(next as ChangePeriod)
                    }
                    aria-label="Period"
                >
                    {PERIODS.filter(({ key }) => changes[key]).map(
                        ({ key, label }) => (
                            <ToggleGroupItem
                                key={key}
                                value={key}
                                className="px-3"
                            >
                                {label}
                            </ToggleGroupItem>
                        ),
                    )}
                </ToggleGroup>
            </CardHeader>

            <CardContent className="space-y-4 px-0">
                <dl className="grid grid-cols-3 divide-x border-y">
                    <Figure label="Change in value">
                        <Gain paise={change.end_paise - change.start_paise} />
                    </Figure>
                    <Figure label="New money">
                        <span className="tabular-nums">
                            {formatSignedPaise(change.net_flow_paise)}
                        </span>
                    </Figure>
                    <Figure label="Market movement">
                        <Gain
                            paise={change.market_paise}
                            pct={change.market_pct}
                        />
                    </Figure>
                </dl>

                {(change.best || change.worst) && (
                    <div className="grid gap-2 px-4 text-sm sm:grid-cols-2 sm:px-6">
                        {change.best && (
                            <Mover label="Moved most" fund={change.best} />
                        )}
                        {change.worst && (
                            <Mover label="Moved least" fund={change.worst} />
                        )}
                    </div>
                )}

                <div className="px-4 sm:px-6">
                    <Explain
                        key={period}
                        period={period}
                        remaining={remaining}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function Figure({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1 px-3 py-3 text-center sm:px-6 sm:text-left">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-display text-sm font-semibold sm:text-lg">
                {children}
            </dd>
        </div>
    );
}

function Mover({ label, fund }: { label: string; fund: ChangeFund }) {
    return (
        <p className="min-w-0 truncate text-muted-foreground">
            {label}:{' '}
            <span className="text-foreground">{fund.name.split(' - ')[0]}</span>{' '}
            <Gain paise={fund.market_paise} pct={fund.market_pct} />
        </p>
    );
}

function Explain({
    period,
    remaining,
}: {
    period: ChangePeriod;
    remaining: number;
}) {
    const ai = useAiRequest(remaining);

    if (ai.loading) {
        return (
            <div className="space-y-2" aria-label="Writing explanation">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-4/5" />
            </div>
        );
    }

    return (
        <div className="space-y-3" aria-live="polite">
            {ai.error && (
                <Alert variant="destructive">
                    <AlertDescription>{ai.error}</AlertDescription>
                </Alert>
            )}
            {ai.result ? (
                <AiAnswer text={ai.result.text} />
            ) : (
                <Button
                    variant="outline"
                    size="sm"
                    className="w-full sm:w-auto"
                    onClick={() => ai.run(digest.url(), { period })}
                >
                    <FontAwesomeIcon icon={faWandMagicSparkles} /> Explain this
                </Button>
            )}
            {(ai.result || ai.error) && (
                <RemainingNote remaining={ai.remaining} />
            )}
        </div>
    );
}
