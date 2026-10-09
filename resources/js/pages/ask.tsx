import { Head, Link } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faPaperPlane,
    faPlus,
    faWandMagicSparkles,
} from '@fortawesome/free-solid-svg-icons';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import AiAnswer, { AiDisclaimer } from '@/components/ai/ai-answer';
import RemainingNote from '@/components/ai/remaining-note';
import { useAiRequest } from '@/components/ai/use-ai-request';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { ask as askPage } from '@/routes';
import { ask } from '@/routes/ai';
import { create } from '@/routes/holdings';

const MAX_QUESTION = 500;
// The server accepts up to 6 earlier turns; older ones are dropped.
const HISTORY_TURNS = 6;

type Turn = { role: 'user' | 'assistant'; content: string };

export default function Ask({
    has_funds: hasFunds,
    remaining,
    suggestions,
}: {
    has_funds: boolean;
    remaining: number;
    suggestions: string[];
}) {
    const [turns, setTurns] = useState<Turn[]>([]);
    const [question, setQuestion] = useState('');
    const ai = useAiRequest(remaining);
    const bottom = useRef<HTMLDivElement>(null);
    const asked = new Set(
        turns
            .filter((turn) => turn.role === 'user')
            .map((turn) => turn.content),
    );
    const unasked = suggestions.filter((suggestion) => !asked.has(suggestion));

    useEffect(() => {
        bottom.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [turns, ai.loading]);

    async function send(text: string) {
        const trimmed = text.trim();

        if (!trimmed || ai.loading) {
            return;
        }

        const history = turns.slice(-HISTORY_TURNS);
        setTurns((current) => [...current, { role: 'user', content: trimmed }]);
        setQuestion('');

        const result = await ai.run(ask.url(), { question: trimmed, history });

        if (result) {
            setTurns((current) => [
                ...current,
                { role: 'assistant', content: result.text },
            ]);
        } else {
            // Let the user retry the same question.
            setTurns((current) => current.slice(0, -1));
            setQuestion(trimmed);
        }
    }

    function onSubmit(event: FormEvent) {
        event.preventDefault();
        void send(question);
    }

    return (
        <>
            <Head title="Ask your portfolio" />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Ask your portfolio"
                    description="Questions are answered from your own figures. The AI explains; it never tells you what to buy or sell."
                />

                {!hasFunds ? (
                    <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed p-10 text-center">
                        <FontAwesomeIcon
                            icon={faWandMagicSparkles}
                            className="size-8 text-muted-foreground"
                        />
                        <p className="text-sm text-muted-foreground">
                            Add a fund and some transactions first, then ask
                            about them here.
                        </p>
                        <Button asChild>
                            <Link href={create()}>
                                <FontAwesomeIcon icon={faPlus} /> Add a fund
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        {turns.length === 0 && (
                            <Suggestions
                                label="Try asking:"
                                questions={unasked}
                                disabled={ai.loading}
                                onPick={send}
                            />
                        )}

                        <div className="space-y-4" aria-live="polite">
                            {turns.map((turn, index) =>
                                turn.role === 'user' ? (
                                    <div
                                        key={index}
                                        className="flex justify-end"
                                    >
                                        <p className="max-w-[85%] rounded-2xl rounded-br-sm bg-primary px-4 py-2 text-sm whitespace-pre-wrap text-primary-foreground">
                                            {turn.content}
                                        </p>
                                    </div>
                                ) : (
                                    <div
                                        key={index}
                                        className="max-w-[95%] rounded-2xl rounded-bl-sm border px-4 py-3"
                                    >
                                        <AiAnswer text={turn.content} />
                                    </div>
                                ),
                            )}

                            {ai.loading && (
                                <div
                                    className="max-w-[95%] space-y-2 rounded-2xl rounded-bl-sm border px-4 py-3"
                                    aria-label="Thinking"
                                >
                                    <Skeleton className="h-4 w-full" />
                                    <Skeleton className="h-4 w-3/4" />
                                </div>
                            )}

                            {ai.error && (
                                <Alert variant="destructive">
                                    <AlertDescription>
                                        {ai.error}
                                    </AlertDescription>
                                </Alert>
                            )}
                            {turns.length > 0 &&
                                !ai.loading &&
                                unasked.length > 0 && (
                                    <Suggestions
                                        label="You could also ask:"
                                        questions={unasked}
                                        disabled={ai.loading}
                                        onPick={send}
                                    />
                                )}
                            <div ref={bottom} />
                        </div>

                        <form onSubmit={onSubmit} className="space-y-2">
                            <div className="flex items-end gap-2">
                                <Textarea
                                    value={question}
                                    onChange={(event) =>
                                        setQuestion(
                                            event.target.value.slice(
                                                0,
                                                MAX_QUESTION,
                                            ),
                                        )
                                    }
                                    onKeyDown={(event) => {
                                        if (
                                            event.key === 'Enter' &&
                                            !event.shiftKey
                                        ) {
                                            event.preventDefault();
                                            void send(question);
                                        }
                                    }}
                                    placeholder="Ask about your funds, returns or allocation"
                                    aria-label="Your question"
                                    maxLength={MAX_QUESTION}
                                    rows={2}
                                    className="max-h-40 resize-none"
                                />
                                <Button
                                    type="submit"
                                    size="icon"
                                    disabled={
                                        ai.loading || question.trim() === ''
                                    }
                                    aria-label="Send"
                                >
                                    <FontAwesomeIcon icon={faPaperPlane} />
                                </Button>
                            </div>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <RemainingNote remaining={ai.remaining} />
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {question.length}/{MAX_QUESTION}
                                </span>
                            </div>
                            <AiDisclaimer />
                        </form>
                    </>
                )}
            </div>
        </>
    );
}

function Suggestions({
    label,
    questions,
    disabled,
    onPick,
}: {
    label: string;
    questions: string[];
    disabled: boolean;
    onPick: (question: string) => void;
}) {
    if (questions.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3">
            <p className="text-sm text-muted-foreground">{label}</p>
            <div className="flex flex-wrap gap-2">
                {questions.map((question) => (
                    <Button
                        key={question}
                        variant="outline"
                        size="sm"
                        className="h-auto py-1.5 text-left whitespace-normal"
                        onClick={() => onPick(question)}
                        disabled={disabled}
                    >
                        {question}
                    </Button>
                ))}
            </div>
        </div>
    );
}

Ask.layout = {
    breadcrumbs: [{ title: 'Ask', href: askPage() }],
};
