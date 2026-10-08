/**
 * POST JSON to a Laravel route with the XSRF token from its cookie. Never
 * throws: network failures come back as status 0 with a friendly message.
 */
export async function postJson<T>(
    url: string,
    body: unknown = {},
): Promise<{
    ok: boolean;
    status: number;
    data: T | null;
    error: string | null;
}> {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
            },
            body: JSON.stringify(body),
        });
        const data = (await response.json().catch(() => null)) as
            | (T & {
                  error?: string;
                  message?: string;
                  errors?: Record<string, string[]>;
              })
            | null;
        const error = response.ok
            ? null
            : (data?.error ??
              Object.values(data?.errors ?? {})[0]?.[0] ??
              (response.status === 429
                  ? 'Too many requests. Please wait a minute and try again.'
                  : 'Something went wrong. Please try again.'));

        return { ok: response.ok, status: response.status, data, error };
    } catch {
        return {
            ok: false,
            status: 0,
            data: null,
            error: "Couldn't reach Hisaab. Check your connection and try again.",
        };
    }
}
