import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { ref } from 'vue';
import { MessagingRequestError, messagingRequest } from '@/lib/messaging';

export type RealtimeSettings = {
    enabled: boolean;
    key?: string;
    host?: string;
    port?: number;
    scheme?: string;
};
export type LiveChange = {
    conversation_id: number | null;
    message_id: number | null;
};
export type TypingChange = {
    conversation_id: number;
    participant_id: number;
    source_id: string;
    typing: boolean;
};
type ConversationListener = {
    changed: (event: LiveChange) => void;
    typing: (event: TypingChange) => void;
    ready: () => void;
    disconnected: () => void;
};

export const messagingConnection = ref<
    'connecting' | 'live' | 'polling' | 'offline' | 'expired'
>('polling');
const updates = new Set<(event: LiveChange) => void>();
const conversations = new Map<number, Set<ConversationListener>>();
let echo: Echo<'reverb'> | null = null;
let userId: number | null = null;
let stopped = true;
let checking = false;
let generation = 0;
let badgeCallback: ((count: number) => void) | null = null;
let badgeTimer: ReturnType<typeof setTimeout> | undefined;

export function onMessagingUpdate(
    callback: (event: LiveChange) => void,
): () => void {
    updates.add(callback);

    return () => {
        updates.delete(callback);
    };
}
function changed(event: LiveChange) {
    updates.forEach((callback) => callback(event));
    conversations.forEach((listeners, id) => {
        if (event.conversation_id === null || event.conversation_id === id) {
            listeners.forEach((listener) => listener.changed(event));
        }
    });
    clearTimeout(badgeTimer);
    badgeTimer = setTimeout(() => {
        void checkSession();
    }, 200);
}
function expire() {
    messagingConnection.value = 'expired';
    echo?.disconnect();
    conversations.forEach((listeners) =>
        listeners.forEach((listener) => listener.disconnected()),
    );
}
async function checkSession() {
    if (stopped || checking || document.visibilityState !== 'visible') {
        return;
    }

    checking = true;
    const current = generation;

    try {
        const response = await messagingRequest<{
            user_id: number;
            unread_count: number;
        }>('/api/messages/session');

        if (current !== generation || stopped) {
            return;
        }

        if (response.user_id !== userId) {
            expire();

            return;
        }

        badgeCallback?.(response.unread_count);
    } catch (error) {
        if (
            current === generation &&
            error instanceof MessagingRequestError &&
            [401, 419].includes(error.status)
        ) {
            expire();
        }
    } finally {
        checking = false;
    }
}
function joinConversation(id: number) {
    if (!echo) {
        return;
    }

    echo.join(`messaging.conversations.${id}`)
        .listen('.messaging.changed', (event: LiveChange) =>
            conversations
                .get(id)
                ?.forEach((listener) => listener.changed(event)),
        )
        .listen('.messaging.typing', (event: TypingChange) =>
            conversations
                .get(id)
                ?.forEach((listener) => listener.typing(event)),
        )
        .subscribed(() =>
            conversations.get(id)?.forEach((listener) => listener.ready()),
        );
}
export function listenToConversation(
    id: number,
    listener: ConversationListener,
): () => void {
    if (!conversations.has(id)) {
        conversations.set(id, new Set());
        joinConversation(id);
    }

    conversations.get(id)!.add(listener);

    return () => {
        const listeners = conversations.get(id);
        listeners?.delete(listener);

        if (!listeners?.size) {
            conversations.delete(id);
            echo?.leave(`messaging.conversations.${id}`);
        }
    };
}
export function connectMessaging(
    id: number,
    settings: RealtimeSettings,
    updateBadge: (count: number) => void,
): () => void {
    stopped = false;
    userId = id;
    const connectedGeneration = ++generation;
    badgeCallback = updateBadge;
    messagingConnection.value = navigator.onLine ? 'polling' : 'offline';

    if (settings.enabled && settings.key) {
        messagingConnection.value = 'connecting';
        echo = new Echo({
            broadcaster: 'reverb',
            Pusher,
            key: settings.key,
            cluster: 'mt1',
            wsHost: settings.host ?? location.hostname,
            wsPort: settings.port ?? 8080,
            wssPort: settings.port ?? 443,
            forceTLS: settings.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            channelAuthorization: {
                customHandler: (params, callback) => {
                    messagingRequest<{ auth: string; channel_data: string }>(
                        '/broadcasting/auth',
                        {
                            method: 'POST',
                            body: {
                                socket_id: params.socketId,
                                channel_name: params.channelName,
                            },
                        },
                    )
                        .then((data) => {
                            if (
                                generation === connectedGeneration &&
                                !stopped
                            ) {
                                callback(null, data);
                            }
                        })
                        .catch((error: unknown) => {
                            if (generation !== connectedGeneration || stopped) {
                                return;
                            }

                            if (
                                error instanceof MessagingRequestError &&
                                [401, 419].includes(error.status)
                            ) {
                                expire();
                            }

                            callback(
                                error instanceof Error
                                    ? error
                                    : new Error('Channel authorization failed'),
                                null,
                            );
                        });
                },
            },
        });
        echo.connector.pusher.connection.bind(
            'state_change',
            ({ current }: { current: string }) => {
                if (
                    stopped ||
                    generation !== connectedGeneration ||
                    messagingConnection.value === 'expired'
                ) {
                    return;
                }

                if (current !== 'connected') {
                    messagingConnection.value = navigator.onLine
                        ? 'polling'
                        : 'offline';
                    conversations.forEach((listeners) =>
                        listeners.forEach((listener) =>
                            listener.disconnected(),
                        ),
                    );
                }
            },
        );
        echo.join(`messaging.users.${id}`)
            .listen('.messaging.changed', (event: LiveChange) => changed(event))
            .subscribed(() => {
                if (
                    stopped ||
                    generation !== connectedGeneration ||
                    messagingConnection.value === 'expired'
                ) {
                    return;
                }

                messagingConnection.value = 'live';
                changed({ conversation_id: null, message_id: null });
            })
            .error(() => {
                if (
                    !stopped &&
                    generation === connectedGeneration &&
                    messagingConnection.value !== 'expired'
                ) {
                    messagingConnection.value = 'polling';
                }
            });
        conversations.forEach((_, conversationId) =>
            joinConversation(conversationId),
        );
    }

    function resume() {
        if (
            document.visibilityState !== 'visible' ||
            messagingConnection.value === 'expired'
        ) {
            return;
        }

        if (!navigator.onLine) {
            messagingConnection.value = 'offline';
        } else if (messagingConnection.value === 'offline') {
            messagingConnection.value = 'polling';
        }

        void checkSession();
        changed({ conversation_id: null, message_id: null });

        if (navigator.onLine) {
            // Echo.connect() creates a new connector; reconnect the existing Pusher instance
            // so authenticated channel subscriptions and their listeners are retained.
            echo?.connector.pusher.connect();
        }
    }
    function offline() {
        messagingConnection.value = 'offline';
        echo?.disconnect();
        conversations.forEach((listeners) =>
            listeners.forEach((listener) => listener.disconnected()),
        );
    }
    const fallback = setInterval(() => {
        if (
            document.visibilityState === 'visible' &&
            navigator.onLine &&
            messagingConnection.value !== 'live' &&
            messagingConnection.value !== 'expired'
        ) {
            changed({ conversation_id: null, message_id: null });
        }
    }, 10000);
    const heartbeat = setInterval(() => {
        void checkSession();
    }, 30000);
    window.addEventListener('online', resume);
    window.addEventListener('offline', offline);
    window.addEventListener('focus', resume);
    document.addEventListener('visibilitychange', resume);

    return () => {
        stopped = true;
        generation++;
        userId = null;
        badgeCallback = null;
        clearInterval(fallback);
        clearInterval(heartbeat);
        clearTimeout(badgeTimer);
        window.removeEventListener('online', resume);
        window.removeEventListener('offline', offline);
        window.removeEventListener('focus', resume);
        document.removeEventListener('visibilitychange', resume);
        echo?.disconnect();
        echo = null;
        messagingConnection.value = 'polling';
    };
}
