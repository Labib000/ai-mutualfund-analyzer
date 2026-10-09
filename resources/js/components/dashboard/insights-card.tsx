import {
    faCircleExclamation,
    faCircleInfo,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { Insight } from '@/types/portfolio';

/**
 * Observations found by fixed rules on the server: no AI call, no quota.
 */
export default function InsightsCard({ insights }: { insights: Insight[] }) {
    return (
        <Card className="gap-0">
            <CardHeader className="pb-2 text-center sm:text-left">
                <CardTitle>Insights</CardTitle>
                <CardDescription>
                    Facts Hisaab noticed in your portfolio. They describe, not
                    advise.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul className="divide-y">
                    {insights.map((insight) => (
                        <li
                            key={`${insight.code}-${insight.title}`}
                            className="flex gap-3 py-3 last:pb-0"
                        >
                            <FontAwesomeIcon
                                icon={
                                    insight.tone === 'attention'
                                        ? faCircleExclamation
                                        : faCircleInfo
                                }
                                className={cn(
                                    'mt-0.5 size-4 shrink-0',
                                    insight.tone === 'attention'
                                        ? 'text-red-700 dark:text-red-400'
                                        : 'text-muted-foreground',
                                )}
                                aria-label={
                                    insight.tone === 'attention'
                                        ? 'Worth a look'
                                        : 'Information'
                                }
                            />
                            <div className="min-w-0 space-y-0.5">
                                <p className="text-sm font-medium">
                                    {insight.title}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {insight.detail}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
