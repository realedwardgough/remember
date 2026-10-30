<template>
    <section
        class="conversation-panel surface flex min-h-0 flex-1 flex-col overflow-hidden"
        aria-label="Conversation"
    >
        <header class="flex shrink-0 items-center gap-3 border-b px-4 py-3">
            <Link
                :href="messagesIndex()"
                preserve-state
                class="flex size-11 shrink-0 items-center justify-center rounded-xl hover:bg-lilac lg:hidden"
                aria-label="Back to messages"
                ><ArrowLeft :size="20"
            /></Link>
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-mint font-semibold"
                aria-hidden="true"
                >{{
                    other?.deleted ? '?' : other?.name.slice(0, 1) || '…'
                }}</span
            >
            <div class="min-w-0 flex-1">
                <h2 class="truncate font-semibold">
                    {{ other?.name || 'Conversation' }}
                </h2>
                <p class="text-xs text-stone-500">
                    {{
                        other?.deleted
                            ? 'Conversation history'
                            : 'Just between you two'
                    }}
                </p>
            </div>
            <button
                type="button"
                class="flex size-11 shrink-0 items-center justify-center rounded-xl text-stone-500 hover:bg-lilac"
                :disabled="loading || refreshing"
                aria-label="Refresh conversation"
                @click="refresh"
            >
                <RefreshCw :size="18" :class="{ 'animate-spin': refreshing }" />
            </button>
        </header>

        <p
            v-if="error"
            class="m-3 rounded-xl bg-blush p-3 text-sm text-red-800"
            role="alert"
        >
            {{ error }}
            <button class="underline" type="button" @click="refresh">
                Try again
            </button>
        </p>
        <div
            ref="scroller"
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-canvas/40 p-4 sm:p-5"
            @scroll="onScroll"
        >
            <p
                v-if="loading"
                role="status"
                class="py-10 text-center text-sm text-stone-500"
            >
                Loading your conversation…
            </p>
            <button
                v-if="beforeId"
                type="button"
                class="button-secondary mx-auto mb-5 flex"
                :disabled="loadingOlder"
                @click="loadOlder"
            >
                {{ loadingOlder ? 'Loading…' : 'Earlier messages' }}
            </button>
            <div
                v-if="
                    !loading &&
                    !error &&
                    !messages.length &&
                    !draft.pending.length
                "
                class="flex h-full min-h-40 flex-col items-center justify-center text-center"
            >
                <MessageCircle class="mb-3 text-primary" :size="30" />
                <p class="story-font text-xl">Start with a little hello</p>
                <p class="mt-2 text-sm text-stone-500">
                    Your messages stay between you and
                    {{ other?.name || 'your family member' }}.
                </p>
            </div>
            <ol class="space-y-4" aria-label="Messages">
                <li v-for="(message, index) in messages" :key="message.id">
                    <p
                        v-if="
                            index === 0 ||
                            day(message.created_at) !==
                                day(messages[index - 1].created_at)
                        "
                        class="my-5 text-center text-xs text-stone-500"
                    >
                        {{ day(message.created_at) }}
                    </p>
                    <div
                        class="flex"
                        :class="
                            message.sender.is_self
                                ? 'justify-end'
                                : 'justify-start'
                        "
                    >
                        <div class="max-w-[88%] sm:max-w-[80%]">
                            <p
                                class="mb-1 px-1 text-xs text-stone-500"
                                :class="{
                                    'text-right': message.sender.is_self,
                                }"
                            >
                                {{
                                    message.sender.is_self
                                        ? 'You'
                                        : message.sender.name
                                }}
                            </p>
                            <p
                                v-if="message.body"
                                class="rounded-2xl px-4 py-3 text-sm leading-6 [overflow-wrap:anywhere] whitespace-pre-wrap"
                                :class="
                                    message.sender.is_self
                                        ? 'rounded-br-sm bg-primary text-white'
                                        : 'rounded-bl-sm border bg-white text-stone-800'
                                "
                            >
                                {{ message.body }}
                            </p>
                            <SharedPostCard
                                v-if="message.shared_post"
                                :share="message.shared_post"
                            />
                            <MessagePhoto
                                v-for="attachment in message.attachments"
                                :key="attachment.id"
                                :attachment="attachment"
                                :now="typingClock"
                                @check="refreshMessage(message.id)"
                                @loaded="photoLoaded"
                            />
                            <time
                                :datetime="message.created_at"
                                class="mt-1 block px-1 text-[11px] text-stone-500"
                                :class="{
                                    'text-right': message.sender.is_self,
                                }"
                                >{{ messageTime(message.created_at) }}</time
                            >
                        </div>
                    </div>
                </li>
                <li
                    v-for="pending in draft.pending"
                    :key="pending.requestId"
                    class="flex justify-end"
                >
                    <div class="max-w-[88%] sm:max-w-[80%]">
                        <div
                            v-if="pending.photos.length"
                            class="mb-2 flex flex-wrap justify-end gap-2"
                        >
                            <PhotoPreview
                                v-for="photo in pending.photos"
                                :key="photo.requestId"
                                :file="photo.file"
                            />
                        </div>
                        <p
                            v-if="pending.body"
                            class="rounded-2xl rounded-br-sm bg-lilac px-4 py-3 text-sm leading-6 [overflow-wrap:anywhere] whitespace-pre-wrap"
                        >
                            {{ pending.body }}
                        </p>
                        <p
                            v-if="pending.status === 'sending'"
                            class="mt-1 text-right text-xs text-stone-500"
                            role="status"
                        >
                            Sending…
                        </p>
                        <div
                            v-else
                            class="mt-2 text-sm text-red-700"
                            role="alert"
                        >
                            <p>{{ pending.error }}</p>
                            <button
                                type="button"
                                class="mt-1 min-h-11 underline"
                                @click="deliver(pending)"
                            >
                                Retry message
                            </button>
                            <button
                                type="button"
                                class="ml-4 min-h-11 text-stone-500 underline"
                                @click="dismissRetry(pending)"
                            >
                                Dismiss retry
                            </button>
                        </div>
                    </div>
                </li>
            </ol>
        </div>
        <p
            v-if="readError"
            role="status"
            class="border-t px-4 py-2 text-xs text-stone-500"
        >
            Your read status couldn’t be saved.
            <button type="button" class="underline" @click="acknowledgeVisible">
                Try again
            </button>
        </p>
        <p
            v-if="conversation && !conversation.can_send"
            class="border-t bg-canvas px-4 py-5 text-sm text-stone-600"
        >
            This account has been deleted. You can still read your conversation.
        </p>
        <div v-if="hasNewMessages" class="border-t bg-lilac p-2 text-center">
            <button
                type="button"
                class="min-h-11 text-sm font-semibold text-primary"
                @click="bottom"
            >
                New messages ↓
            </button>
        </div>
        <p
            class="min-h-6 shrink-0 px-4 py-1 text-xs text-stone-500"
            role="status"
        >
            {{ typingText || connectionText }}
        </p>
        <MessageComposer
            v-if="!conversation || conversation.can_send"
            v-model="draft.body"
            v-model:photos="draft.photos"
            :images-enabled="page.props.messageImages.enabled"
            :retention-hours="page.props.messageImages.retention_hours ?? null"
            :disabled="
                !conversation || loading || messagingConnection === 'expired'
            "
            @send="sendDraft"
            @blur="stopTyping"
        />
    </section>
</template>
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, MessageCircle, RefreshCw } from 'lucide-vue-next';
import {
    computed,
    inject,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import MessageComposer from '@/components/messaging/MessageComposer.vue';
import MessagePhoto from '@/components/messaging/MessagePhoto.vue';
import PhotoPreview from '@/components/messaging/PhotoPreview.vue';
import SharedPostCard from '@/components/messaging/SharedPostCard.vue';
import { messageDraftsKey } from '@/composables/useMessageDrafts';
import {
    MessagingRequestError,
    messageError,
    messageTime,
    messagingRequest,
} from '@/lib/messaging';
import {
    listenToConversation,
    messagingConnection,
} from '@/lib/messaging-live';
import type { LiveChange, TypingChange } from '@/lib/messaging-live';
import { index as messagesIndex } from '@/routes/messages';
import {
    read as readRoute,
    message as messageRoute,
    typing as typingRoute,
    send as sendRoute,
    show as historyRoute,
} from '@/routes/messaging';
import * as attachmentRoutes from '@/routes/messaging/attachments';
import type { PageProps } from '@/types';
import type {
    DirectConversation,
    MessageAttachment,
    DirectMessage,
    MessageHistory,
    PendingMessage,
} from '@/types/messaging';

const props = defineProps<{ conversationId: number; obscured: boolean }>();
const page = usePage<PageProps>();
const drafts = inject(messageDraftsKey)!;
const draft = drafts.forConversation(props.conversationId);
const emit = defineEmits<{
    changed: [conversationId: number, read: boolean];
}>();
const messages = ref<DirectMessage[]>([]);
const conversation = ref<DirectConversation | null>(null);
const other = computed(() =>
    conversation.value?.participants.find(
        (participant) => !participant.is_self,
    ),
);
const loading = ref(true);
const refreshing = ref(false);
const loadingOlder = ref(false);
const error = ref('');
const readError = ref(false);
const beforeId = ref<number | null>(null);
const readToken = ref<string | null>(null);
const acknowledgedToken = ref<string | null>(null);
const scroller = ref<HTMLDivElement | null>(null);
let acknowledging = false;
let alive = true;
const controller = new AbortController();
const hasNewMessages = ref(false);
const typingPeers = reactive<Record<string, number>>({});
const typingClock = ref(Date.now());
const typingText = computed(() =>
    Object.values(typingPeers).some((expiry) => expiry > typingClock.value) &&
    other.value &&
    !other.value.deleted
        ? `${other.value.name} is typing…`
        : '',
);
const connectionText = computed(
    () =>
        ({
            live: 'Messages update automatically',
            connecting: 'Connecting…',
            polling: 'Checking for new messages…',
            offline: 'You’re offline. We’ll reconnect when you’re back.',
            expired: 'Your session has expired. Reload to sign in again.',
        })[messagingConnection.value],
);
const sourceId = crypto.randomUUID();
let lastTypingAt = 0;
let sentTyping = false;
let typingTimeout: ReturnType<typeof setTimeout> | undefined;
let typingTimer: ReturnType<typeof setInterval> | undefined;
let stopLive: (() => void) | undefined;
const checkingPhotos = new Set<number>();
async function refreshMessage(id: number) {
    if (!alive || checkingPhotos.has(id)) {
        return;
    }

    checkingPhotos.add(id);

    try {
        const response = await messagingRequest<{ data: DirectMessage }>(
            messageRoute.url({
                conversation: props.conversationId,
                message: id,
            }),
            { signal: controller.signal },
        );

        if (alive) {
            merge([response.data]);
        }
    } catch {
        /* Polling or a later live update will retry unavailable photo metadata. */
    } finally {
        checkingPhotos.delete(id);
    }
}
function photoLoaded() {
    if (hasNewMessages.value || !nearBottom()) {
        return;
    }

    void bottom();
}
function liveChanged(event: LiveChange) {
    clearTyping();

    if (
        event.message_id &&
        messages.value.some((message) => message.id === event.message_id)
    ) {
        void refreshMessage(event.message_id);
    }

    if (event.message_id === null) {
        messages.value
            .filter((message) => message.shared_post !== null)
            .forEach((message) => {
                void refreshMessage(message.id);
            });
    }

    requestSync();
}
let syncing = false;
let syncQueued = false;
let syncTimer: ReturnType<typeof setTimeout> | undefined;
function clearTyping() {
    Object.keys(typingPeers).forEach((key) => delete typingPeers[key]);
}
function receiveTyping(event: TypingChange) {
    const participant = conversation.value?.participants.find(
        (person) => person.id === event.participant_id,
    );

    if (
        !participant ||
        participant.is_self ||
        participant.deleted ||
        event.conversation_id !== props.conversationId
    ) {
        return;
    }

    const key = `${event.participant_id}.${event.source_id}`;

    if (event.typing) {
        typingPeers[key] = Date.now() + 6000;
    } else {
        delete typingPeers[key];
    }

    typingClock.value = Date.now();
}
function sendTyping(active: boolean) {
    if (
        messagingConnection.value !== 'live' ||
        !conversation.value?.can_send ||
        (active && props.obscured)
    ) {
        return;
    }

    if (!active && !sentTyping) {
        return;
    }

    if (active && sentTyping && Date.now() - lastTypingAt < 2000) {
        return;
    }

    sentTyping = active;
    lastTypingAt = Date.now();
    void messagingRequest(typingRoute.url(props.conversationId), {
        method: 'POST',
        body: { typing: active, source_id: sourceId },
    }).catch(() => {});
}
function stopTyping() {
    clearTimeout(typingTimeout);
    sendTyping(false);
}
function requestSync() {
    clearTimeout(syncTimer);
    syncTimer = setTimeout(() => {
        void sync();
    }, 80);
}
function drainSync() {
    if (syncQueued && alive) {
        syncQueued = false;
        requestSync();
    }
}
function updateConversation(value: DirectConversation) {
    conversation.value = value;
    messages.value = messages.value.map((message) => ({
        ...message,
        sender:
            value.participants.find(
                (person) => person.id === message.sender.id,
            ) ?? message.sender,
    }));

    if (!value.can_send) {
        clearTyping();
    }
}
async function sync() {
    if (
        !alive ||
        document.visibilityState !== 'visible' ||
        messagingConnection.value === 'expired'
    ) {
        return;
    }

    if (loading.value || refreshing.value || loadingOlder.value || syncing) {
        syncQueued = true;

        return;
    }

    if (!messages.value.length) {
        await refresh();

        return;
    }

    syncing = true;
    const follow = nearBottom();

    try {
        let more: number | null;

        do {
            const after = messages.value.at(-1)!.id;
            const history = await messagingRequest<MessageHistory>(
                historyRoute.url(props.conversationId, {
                    query: { after_id: after },
                }),
                { signal: controller.signal },
            );

            if (!alive) {
                return;
            }

            updateConversation(history.conversation);
            merge(history.data);

            if (history.read_token) {
                readToken.value = history.read_token;
            }

            if (history.data.length && !follow) {
                hasNewMessages.value = true;
            }

            more = history.next_after_id;
        } while (more && alive);

        error.value = '';

        if (follow) {
            await bottom();
        }
    } catch (failure) {
        if (alive) {
            error.value = messageError(failure);
        }
    } finally {
        syncing = false;
        drainSync();
    }
}
function onScroll() {
    if (nearBottom()) {
        hasNewMessages.value = false;
    }

    void acknowledgeVisible();
}

function day(date: string) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(
        new Date(date),
    );
}
function nearBottom() {
    const element = scroller.value;

    return (
        element &&
        element.scrollHeight - element.scrollTop - element.clientHeight < 48
    );
}
async function bottom() {
    hasNewMessages.value = false;
    await nextTick();

    if (alive && scroller.value) {
        scroller.value.scrollTop = scroller.value.scrollHeight;
    }

    await acknowledgeVisible();
}
function merge(incoming: DirectMessage[]) {
    const merged = new Map(
        messages.value.map((message) => [message.id, message]),
    );

    for (const message of incoming) {
        merged.set(message.id, message);
    }

    messages.value = [...merged.values()].sort(
        (first, second) => first.id - second.id,
    );
    draft.pending.splice(
        0,
        draft.pending.length,
        ...draft.pending.filter(
            (pending) =>
                !incoming.some(
                    (message) =>
                        message.client_request_id === pending.requestId,
                ),
        ),
    );
}
async function refresh() {
    if (refreshing.value) {
        return;
    }

    refreshing.value = true;
    error.value = '';

    try {
        const history = await messagingRequest<MessageHistory>(
            historyRoute.url(props.conversationId),
            { signal: controller.signal },
        );

        if (!alive) {
            return;
        }

        // Replace sender metadata too, so account deletion cannot leave old names in loaded history.
        messages.value = history.data;
        merge(history.data);
        updateConversation(history.conversation);
        beforeId.value = history.next_before_id;
        readToken.value = history.read_token;
        loading.value = false;
        await bottom();
        emit('changed', props.conversationId, false);
    } catch (failure) {
        if (alive) {
            error.value = messageError(failure);
        }
    } finally {
        loading.value = false;
        refreshing.value = false;
        drainSync();
    }
}
async function loadOlder() {
    if (!beforeId.value || loadingOlder.value) {
        return;
    }

    loadingOlder.value = true;
    error.value = '';
    const height = scroller.value?.scrollHeight ?? 0;
    const scrollTop = scroller.value?.scrollTop ?? 0;

    try {
        const history = await messagingRequest<MessageHistory>(
            historyRoute.url(props.conversationId, {
                query: { before_id: beforeId.value },
            }),
            { signal: controller.signal },
        );

        if (!alive) {
            return;
        }

        merge(history.data);
        updateConversation(history.conversation);
        beforeId.value = history.next_before_id;
        await nextTick();

        if (scroller.value) {
            scroller.value.scrollTop =
                scrollTop + scroller.value.scrollHeight - height;
        }
    } catch (failure) {
        if (alive) {
            error.value = messageError(failure);
        }
    } finally {
        loadingOlder.value = false;
        drainSync();
    }
}
async function acknowledgeVisible() {
    if (
        !alive ||
        props.obscured ||
        document.visibilityState !== 'visible' ||
        !document.hasFocus() ||
        !nearBottom() ||
        !readToken.value ||
        acknowledgedToken.value === readToken.value ||
        acknowledging
    ) {
        return;
    }

    const token = readToken.value;
    acknowledging = true;

    try {
        await messagingRequest(readRoute.url(props.conversationId), {
            method: 'PUT',
            body: { read_token: token },
        });
        acknowledgedToken.value = token;
        readError.value = false;

        if (alive) {
            emit('changed', props.conversationId, true);
        }
    } catch {
        if (alive) {
            readError.value = true;
        }
    } finally {
        acknowledging = false;
    }
}
async function sendDraft() {
    stopTyping();
    const body = draft.body.trim();

    if ((!body && !draft.photos.length) || !conversation.value?.can_send) {
        return;
    }

    const pending = reactive<PendingMessage>({
        requestId: crypto.randomUUID(),
        body,
        photos: draft.photos.map((file) => ({
            file,
            requestId: crypto.randomUUID(),
        })),
        status: 'sending',
    });
    draft.pending.push(pending);
    draft.body = '';
    draft.photos = [];
    await bottom();
    await deliver(pending);
}
async function deliver(pending: PendingMessage) {
    pending.status = 'sending';
    pending.error = undefined;

    try {
        for (const photo of pending.photos) {
            if (!photo.uploaded) {
                const form = new FormData();
                form.append('image', photo.file);
                form.append('client_request_id', photo.requestId);
                const uploaded = await messagingRequest<{
                    data: MessageAttachment;
                }>(attachmentRoutes.store.url(props.conversationId), {
                    method: 'POST',
                    body: form,
                });
                photo.uploaded = uploaded.data;
            }
        }

        const response = await messagingRequest<{ data: DirectMessage }>(
            sendRoute.url(props.conversationId),
            {
                method: 'POST',
                body: {
                    body: pending.body,
                    client_request_id: pending.requestId,
                    attachment_ids: pending.photos.map(
                        (photo) => photo.uploaded!.id,
                    ),
                },
            },
        );
        // Resolve pending state even if this thread has unmounted while its send completes.
        const index = draft.pending.findIndex(
            (message) => message.requestId === pending.requestId,
        );

        if (index !== -1) {
            draft.pending.splice(index, 1);
        }

        if (alive) {
            merge([response.data]);
            emit('changed', props.conversationId, false);
            await bottom();
        }
    } catch (failure) {
        if (
            failure instanceof MessagingRequestError &&
            failure.status === 422 &&
            failure.fields.includes('attachment_ids')
        ) {
            // This validation error confirms the send was not accepted; a retry can safely upload fresh copies.
            pending.photos.forEach((photo) => {
                photo.uploaded = undefined;
                photo.requestId = crypto.randomUUID();
            });
        }

        pending.status = 'failed';
        pending.error = messageError(failure);
    }
}
function dismissRetry(pending: PendingMessage) {
    const index = draft.pending.findIndex(
        (message) => message.requestId === pending.requestId,
    );

    if (index !== -1) {
        draft.pending.splice(index, 1);
    }
}
function onVisibility() {
    if (document.visibilityState === 'visible') {
        void acknowledgeVisible();
    } else {
        stopTyping();
        clearTyping();
    }
}
watch(
    () => props.obscured,
    (obscured) => {
        if (!obscured) {
            void acknowledgeVisible();
        } else {
            stopTyping();
        }
    },
);
watch(
    () => draft.body,
    (body) => {
        clearTimeout(typingTimeout);

        if (!body.trim()) {
            stopTyping();

            return;
        }

        sendTyping(true);
        typingTimeout = setTimeout(stopTyping, 3000);
    },
);
onMounted(() => {
    stopLive = listenToConversation(props.conversationId, {
        changed: liveChanged,
        typing: receiveTyping,
        ready: requestSync,
        disconnected: () => {
            clearTyping();
            sentTyping = false;
        },
    });
    typingTimer = setInterval(() => {
        typingClock.value = Date.now();
    }, 1000);
    void refresh();
    window.addEventListener('focus', onVisibility);
    document.addEventListener('visibilitychange', onVisibility);
});
onBeforeUnmount(() => {
    stopTyping();
    stopLive?.();
    clearInterval(typingTimer);
    clearTimeout(typingTimeout);
    clearTimeout(syncTimer);
    alive = false;
    controller.abort();
    window.removeEventListener('focus', onVisibility);
    document.removeEventListener('visibilitychange', onVisibility);
});
</script>
