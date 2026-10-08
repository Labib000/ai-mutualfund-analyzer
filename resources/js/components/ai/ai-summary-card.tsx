import { RefreshCw, Sparkles } from 'lucide-react';
import AiAnswer from '@/components/ai/ai-answer';
import RemainingNote from '@/components/ai/remaining-note';
import { useAiRequest } from '@/components/ai/use-ai-request';
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
import { summary } from '@/routes/ai';

export default function AiSummaryCard({ remaining }: { remaining: number }) {
    const ai = useAiRequest(remaining);

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                <div className="space-y-1.5">
                    <CardTitle className="flex items-center gap-2">
                        <Sparkles className="size-4" aria-hidden="true" />
                        AI summary
                    </CardTitle>
                    <CardDescription>
                        A plain-language explanation of your figures.
                    </CardDescription>
                </div>
                {ai.result ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={ai.loading}
                        onClick={() => ai.run(summary.url(), { fresh: true })}
                    >
                        <RefreshCw /> Regenerate
                    </Button>
                ) : (
                    <Button
                        disabled={ai.loading}
                        onClick={() => ai.run(summary.url())}
                    >
                        <Sparkles /> Summarise my portfolio
                    </Button>
                )}
            </CardHeader>

            {(ai.loading || ai.result || ai.error) && (
                <CardContent className="space-y-3" aria-live="polite">
                    {ai.loading ? (
                        <div className="space-y-2" aria-label="Writing summary">
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-4 w-11/12" />
                            <Skeleton className="h-4 w-4/5" />
                        </div>
                    ) : ai.error ? (
                        <Alert variant="destructive">
                            <AlertDescription>{ai.error}</AlertDescription>
                        </Alert>
                    ) : (
                        ai.result && <AiAnswer text={ai.result.text} />
                    )}
                    <RemainingNote remaining={ai.remaining} />
                </CardContent>
            )}
        </Card>
    );
}
