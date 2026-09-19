import axios from 'axios';

/**
 * JSON calls to our own backend.
 *
 * axios rather than fetch, specifically because of CSRF.
 *
 * The Blade layout renders <meta name="csrf-token"> once, at the last full
 * page load. But signing in calls session()->regenerate(), which mints a NEW
 * token — and Inertia performs that login over XHR without re-rendering the
 * layout. From then on the meta tag holds a token the session no longer
 * recognises, and anything that reads it is rejected with 419 "CSRF token
 * mismatch" for the rest of the session.
 *
 * axios reads the XSRF-TOKEN cookie instead, which Laravel rewrites on every
 * response and so is never stale. It is what Inertia's own router.post uses,
 * which is why form submissions kept working while hand-rolled fetch calls
 * did not.
 *
 * Only POSTs need this. A GET carries no CSRF check, so the search endpoints
 * are left on fetch.
 */
export async function postJson<T>(url: string, body: unknown, signal?: AbortSignal): Promise<T> {
    const response = await axios.post<T>(url, body, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal,
    });

    return response.data;
}

/** True when the caller aborted, which is not a failure worth showing anyone. */
export function isAbort(error: unknown): boolean {
    return axios.isCancel(error) || (error as { code?: string } | null)?.code === 'ERR_CANCELED';
}

/**
 * The most useful sentence in a Laravel error response.
 *
 * A 422 names the offending field, which is nearly always more helpful than
 * the generic message above it — "That phone number is already in use" rather
 * than "The given data was invalid".
 */
export function errorMessage(error: unknown, fallback: string): string {
    const data = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } } | null)
        ?.response?.data;

    const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined;

    return firstFieldError ?? data?.message ?? fallback;
}
