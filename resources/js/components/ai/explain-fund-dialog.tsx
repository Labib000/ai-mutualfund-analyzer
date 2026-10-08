import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import AiAnswer from '@/components/ai/ai-answer';
import RemainingNote from '@/components/ai/remaining-note';
import { useAiRequest } from '@/components/ai/use-ai-request';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { explain } from '@/routes/holdings';

export default function ExplainFundDialog({
    holdingId,
    schemeName,
    remaining,
}: {
    holdingId: number;
    schemeName: string;
    remaining: number;
}) {
    const [open, setOpen] = useState(false);
    const ai = useAiRequest(remaining);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next && !ai.result && !ai.loading) {
                    void ai.run(explain.url(holdingId));
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Sparkles /> Explain this fund
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogTitle className="pr-8">{schemeName}</DialogTitle>
                <DialogDescription>
                    What this kind of fund is and how it works.
                </DialogDescription>

                <div aria-live="polite" className="space-y-3">
                    {ai.loading ? (
                        <div
                            className="space-y-2"
                            aria-label="Writing explanation"
                        >
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-4 w-11/12" />
                            <Skeleton className="h-4 w-4/5" />
                            <Skeleton className="h-4 w-3/5" />
                        </div>
                    ) : ai.error ? (
                        <div className="space-y-3">
                            <Alert variant="destructive">
                                <AlertDescription>{ai.error}</AlertDescription>
                            </Alert>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => ai.run(explain.url(holdingId))}
                            >
                                Try again
                            </Button>
                        </div>
                    ) : (
                        ai.result && <AiAnswer text={ai.result.text} />
                    )}
                    <RemainingNote remaining={ai.remaining} />
                </div>
            </DialogContent>
        </Dialog>
    );
}
