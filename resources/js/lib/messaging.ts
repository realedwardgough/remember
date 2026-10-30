export class MessagingRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly fields: string[] = [],
    ) {
        super(message);
    }
}

export async function messagingRequest<T>(
    url: string,
    options: { method?: string; body?: unknown; signal?: AbortSignal } = {},
): Promise<T> {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const response = await fetch(url, {
        method: options.method ?? 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        signal: options.signal,
        headers: {
            Accept: 'application/json',
            ...(options.body instanceof FormData
                ? {}
                : { 'Content-Type': 'application/json' }),
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
        },
        body:
            options.body instanceof FormData
                ? options.body
                : options.body === undefined
                  ? undefined
                  : JSON.stringify(options.body),
    });

    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        const validation = Object.values(data.errors ?? {}).flat()[0];
        const message =
            response.status === 401 || response.status === 419
                ? 'Your session has expired. Reload this page to sign in again.'
                : response.status === 429
                  ? 'Please wait a moment before trying again.'
                  : response.status === 403
                    ? 'This conversation is no longer available for this action.'
                    : typeof validation === 'string'
                      ? validation
                      : 'We couldn’t complete that request. Please try again.';

        throw new MessagingRequestError(
            message,
            response.status,
            Object.keys(data.errors ?? {}),
        );
    }

    return response.status === 204 ? (undefined as T) : response.json();
}

export function messageError(error: unknown): string {
    return error instanceof MessagingRequestError
        ? error.message
        : 'Couldn’t connect. Check your connection and try again.';
}

export function messageTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(date));
}

export function conversationDate(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    }).format(new Date(date));
}
