import { Sparkles } from 'lucide-react';
import { cn } from '@/lib/utils';

export const AI_DISCLAIMER =
    "AI-generated explanation for information only. It isn't investment advice; for decisions, consult a SEBI-registered investment adviser.";

/**
 * Renders model text as plain React text: paragraphs, and "- " lines as
 * lists. Nothing is interpreted as HTML or markdown.
 */
export default function AiAnswer({
    text,
    className,
    showDisclaimer = true,
}: {
    text: string;
    className?: string;
    showDisclaimer?: boolean;
}) {
    return (
        <div className={cn('space-y-3', className)}>
            <div className="space-y-3 text-sm leading-relaxed">
                {toBlocks(text).map((block, index) =>
                    block.type === 'list' ? (
                        <ul key={index} className="list-disc space-y-1 pl-5">
                            {block.items.map((item, itemIndex) => (
                                <li key={itemIndex}>{item}</li>
                            ))}
                        </ul>
                    ) : (
                        <p key={index}>{block.text}</p>
                    ),
                )}
            </div>
            {showDisclaimer && <AiDisclaimer />}
        </div>
    );
}

export function AiDisclaimer() {
    return (
        <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
            <Sparkles className="mt-0.5 size-3 shrink-0" aria-hidden="true" />
            <span>{AI_DISCLAIMER}</span>
        </p>
    );
}

type Block =
    | { type: 'paragraph'; text: string }
    | { type: 'list'; items: string[] };

function toBlocks(text: string): Block[] {
    const blocks: Block[] = [];
    let paragraph: string[] = [];

    const flushParagraph = () => {
        if (paragraph.length > 0) {
            blocks.push({ type: 'paragraph', text: paragraph.join(' ') });
            paragraph = [];
        }
    };

    for (const rawLine of text.split('\n')) {
        // Models sometimes add markdown emphasis despite instructions; drop the markers.
        const line = rawLine.replace(/\*\*|__/g, '').trim();
        const bullet = line.match(/^(?:[-*•]|\d+[.)])\s+(.*)$/);

        if (line === '') {
            flushParagraph();
        } else if (bullet) {
            flushParagraph();
            const last = blocks[blocks.length - 1];

            if (last?.type === 'list') {
                last.items.push(bullet[1]);
            } else {
                blocks.push({ type: 'list', items: [bullet[1]] });
            }
        } else {
            paragraph.push(line);
        }
    }

    flushParagraph();

    return blocks;
}
