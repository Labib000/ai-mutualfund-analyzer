import {
    faRotate,
    faWandMagicSparkles,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { AnimatePresence, motion } from 'motion/react';
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

const fade = {
    initial: { opacity: 0, y: 6 },
    animate: { opacity: 1, y: 0 },
    exit: { opacity: 0, y: -6 },
    transition: { duration: 0.25 },
};

export default function AiSummaryCard({ remaining }: { remaining: number }) {
    const ai = useAiRequest(remaining);
    const state = ai.loading
        ? 'loading'
        : ai.error
          ? 'error'
          : ai.result
            ? 'result'
            : null;

    return (
        <Card className="gap-0 overflow-hidden">
            <CardHeader className="flex flex-col items-center gap-4 text-center sm:flex-row sm:flex-wrap sm:justify-between sm:text-left">
                <div className="min-w-0 space-y-1">
                    <CardTitle className="flex items-center justify-center gap-2 sm:justify-start">
                        <FontAwesomeIcon
                            icon={faWandMagicSparkles}
                            className="size-3.5 text-primary"
                            aria-hidden="true"
                        />
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
                        <FontAwesomeIcon icon={faRotate} spin={ai.loading} />{' '}
                        Regenerate
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        disabled={ai.loading}
                        onClick={() => ai.run(summary.url())}
                        className="w-full sm:w-auto"
                    >
                        <FontAwesomeIcon icon={faWandMagicSparkles} /> Summarise
                        my portfolio
                    </Button>
                )}
            </CardHeader>

            <motion.div
                layout
                transition={{ duration: 0.3, ease: 'easeOut' }}
                aria-live="polite"
            >
                <AnimatePresence mode="wait" initial={false}>
                    {state && (
                        <motion.div key={state} {...fade}>
                            <CardContent className="space-y-3 pt-5">
                                {state === 'loading' ? (
                                    <div
                                        className="space-y-2"
                                        aria-label="Writing summary"
                                    >
                                        <Skeleton className="h-4 w-full" />
                                        <Skeleton className="h-4 w-11/12" />
                                        <Skeleton className="h-4 w-4/5" />
                                    </div>
                                ) : state === 'error' ? (
                                    <Alert variant="destructive">
                                        <AlertDescription>
                                            {ai.error}
                                        </AlertDescription>
                                    </Alert>
                                ) : (
                                    ai.result && (
                                        <AiAnswer text={ai.result.text} />
                                    )
                                )}
                                <RemainingNote remaining={ai.remaining} />
                            </CardContent>
                        </motion.div>
                    )}
                </AnimatePresence>
            </motion.div>
        </Card>
    );
}
