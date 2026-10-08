import { useState } from 'react';
import { postJson } from '@/lib/post-json';

export type AiResult = {
    text: string;
    cached?: boolean;
    generated_at?: string;
    remaining: number;
};

/**
 * Calls an AI endpoint, tracking loading, the latest answer, errors and the
 * remaining monthly requests.
 */
export function useAiRequest(initialRemaining: number | null = null) {
    const [result, setResult] = useState<AiResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [remaining, setRemaining] = useState<number | null>(initialRemaining);

    async function run(
        url: string,
        body: unknown = {},
    ): Promise<AiResult | null> {
        setLoading(true);
        setError(null);

        const response = await postJson<AiResult & { remaining?: number }>(
            url,
            body,
        );

        setLoading(false);

        if (typeof response.data?.remaining === 'number') {
            setRemaining(response.data.remaining);
        }

        if (!response.ok || !response.data?.text) {
            setError(
                response.error ?? 'Something went wrong. Please try again.',
            );

            return null;
        }

        setResult(response.data);

        return response.data;
    }

    return { run, result, error, loading, remaining };
}
