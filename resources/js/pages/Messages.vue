<template>
    <BaseLayout>
        <template #sidebar
            ><Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
        /></template>
        <Head title="Messages" />
        <div
            class="messages-workspace flex min-h-0 flex-col gap-5"
            :class="{ 'messages-workspace-open': conversationId }"
            :style="workspaceStyle"
        >
            <PageHeader
                title="Little conversations"
                eyebrow="Your messages"
                description="A place to catch up, just between you two."
                :class="{ 'hidden lg:flex': conversationId }"
            >
                <button
                    type="button"
                    class="button-primary"
                    @click="activeModal = 'contact'"
                >
                    <Plus :size="18" /> New message
                </button>
            </PageHeader>
            <div
                class="grid min-h-0 flex-1 gap-5 lg:grid-cols-[18rem_minmax(0,1fr)]"
            >
                <section
                    class="surface flex min-h-0 flex-col overflow-hidden"
                    :class="{ 'hidden lg:flex': conversationId }"
                    aria-labelledby="inbox-heading"
                >
                    <header
                        class="flex items-center justify-between border-b px-4 py-3"
                    >
                        <h2 id="inbox-heading" class="font-semibold">
                            Your conversations
                        </h2>
                        <button
                            type="button"
                            class="flex size-11 items-center justify-center rounded-xl text-stone-500 hover:bg-lilac"
                            :disabled="inboxLoading"
                            aria-label="Refresh messages"
                            @click="loadInbox()"
                        >
                            <RefreshCw
                                :size="18"
                                :class="{ 'animate-spin': inboxLoading }"
                            />
                        </button>
                    </header>
                    <p
                        v-if="inboxError"
                        role="alert"
                        class="m-3 rounded-xl bg-blush p-3 text-sm text-red-800"
                    >
                        {{ inboxError }}
                        <button
                            type="button"
                            class="underline"
                            @click="loadInbox()"
                        >
                            Try again
                        </button>
                    </p>
                    <div class="min-h-0 flex-1 overflow-y-auto p-2">
                        <p
                            v-if="inboxLoading && !inbox.length"
                            class="p-5 text-center text-sm text-stone-500"
                            role="status"
                        >
                            Loading conversations…
                        </p>
                        <div
                            v-else-if="!inbox.length && !inboxError"
                            class="px-4 py-10 text-center"
                        >
                            <MessageCircle
                                :size="32"
                                class="mx-auto mb-4 text-primary"
                            />
                            <p class="story-font text-xl">Say a little hello</p>
                            <p class="mt-2 text-sm leading-6 text-stone-500">
                                Choose someone in your family and start a
                                conversation.
                            </p>
                            <button
                                type="button"
                                class="button-primary mt-5"
                                @click="activeModal = 'contact'"
                            >
                                Start a conversation
                            </button>
                        </div>
                        <ul class="space-y-1">
                            <li
                                v-for="conversation in sortedInbox"
                                :key="conversation.id"
                            >
                                <Link
                                    :href="showConversation(conversation.id)"
                                    preserve-state
                                    class="flex min-h-22 items-center gap-3 rounded-xl p-3"
                                    :class="
                                        conversation.id === conversationId
                                            ? 'bg-lilac'
                                            : 'hover:bg-canvas'
                                    "
                                    :aria-current="
                                        conversation.id === conversationId
                                            ? 'page'
                                            : undefined
                                    "
                                >
                                    <span
                                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-mint font-semibold"
                                        aria-hidden="true"
                                        >{{
                                            recipient(conversation)?.deleted
                                                ? '?'
                                                : recipient(
                                                      conversation,
                                                  )?.name.slice(0, 1)
                                        }}</span
                                    >
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="flex items-center justify-between gap-2"
                                            ><span
                                                class="truncate text-sm font-semibold"
                                                >{{
                                                    recipient(conversation)
                                                        ?.name
                                                }}</span
                                            ><time
                                                :datetime="
                                                    conversation.updated_at
                                                "
                                                class="shrink-0 text-[10px] text-stone-500"
                                                >{{
                                                    conversationDate(
                                                        conversation.updated_at,
                                                    )
                                                }}</time
                                            ></span
                                        >
                                        <span
                                            class="mt-1 flex items-center gap-2"
                                            ><span
                                                class="truncate text-xs text-stone-500"
                                                >{{
                                                    preview(conversation)
                                                }}</span
                                            ><span
                                                v-if="conversation.unread_count"
                                                class="ml-auto min-w-5 shrink-0 rounded-full bg-primary px-1 text-center text-[10px] leading-5 text-white"
                                                :aria-label="`${conversation.unread_count} unread messages`"
                                                >{{
                                                    conversation.unread_count >
                                                    99
                                                        ? '99+'
                                                        : conversation.unread_count
                                                }}</span
                                            ></span
                                        >
                                    </span>
                                </Link>
                            </li>
                        </ul>
                        <button
                            v-if="inboxCursor"
                            type="button"
                            class="button-secondary mt-3 w-full"
                            :disabled="inboxLoading"
                            @click="loadInbox(true)"
                        >
                            More conversations
                        </button>
                    </div>
                </section>
                <ConversationThread
                    v-if="conversationId"
                    :key="conversationId"
                    :conversation-id="conversationId"
                    :obscured="activeModal !== null"
                    @changed="conversationChanged"
                />
                <section
                    v-else
                    class="surface hidden flex-col items-center justify-center p-8 text-center lg:flex"
                >
                    <span
                        class="mb-5 flex size-18 items-center justify-center rounded-full bg-lilac text-primary"
                        ><MessageCircle :size="30"
                    /></span>
                    <h2 class="story-font text-3xl">
                        A little closer, wherever you are
                    </h2>
                    <p class="mt-3 max-w-xs text-sm leading-6 text-stone-500">
                        Pick a conversation or start a new one with someone in
                        your family.
                    </p>
                    <button
                        type="button"
                        class="button-primary mt-6"
                        @click="activeModal = 'contact'"
                    >
                        New message
                    </button>
                </section>
            </div>
        </div>
        <Modal
            :show="activeModal === 'contact'"
            title="Who shall we say hello to?"
            eyebrow="New message"
            @close="activeModal = null"
            ><ContactPicker
                v-if="activeModal === 'contact'"
                @open="openConversation"
        /></Modal>
        <Modal
            :show="activeModal === 'post'"
            mobile-fullscreen
            title="Add a memory"
            eyebrow="New memory"
            @close="activeModal = null"
            ><PostForm @close="activeModal = null"
        /></Modal>
        <Modal
            :show="activeModal === 'search'"
            title="Search the timeline"
            eyebrow="Find memories"
            @close="activeModal = null"
            ><Search @close="activeModal = null"
        /></Modal>
    </BaseLayout>
</template>
<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { MessageCircle, Plus, RefreshCw } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue';
import ContactPicker from '@/components/messaging/ContactPicker.vue';
import ConversationThread from '@/components/messaging/ConversationThread.vue';
import Modal from '@/components/Modal.vue';
import PageHeader from '@/components/PageHeader.vue';
import PostForm from '@/components/PostForm.vue';
import Search from '@/components/Search.vue';
import Sidebar from '@/components/Sidebar.vue';
import {
    createMessageDrafts,
    messageDraftsKey,
} from '@/composables/useMessageDrafts';
import BaseLayout from '@/layouts/BaseLayout.vue';
import {
    conversationDate,
    messageError,
    messagingRequest,
} from '@/lib/messaging';
import { show as showConversation } from '@/routes/messages';
import { index as inboxRoute } from '@/routes/messaging';
import type { PageProps } from '@/types';
import type { CursorPage, DirectConversation } from '@/types/messaging';

defineProps<{ conversationId: number | null }>();
const page = usePage<PageProps>();
const activeModal = ref<'contact' | 'post' | 'search' | null>(null);
const inbox = ref<DirectConversation[]>([]);
const inboxCursor = ref<string | null>(null);
const inboxLoading = ref(false);
const inboxError = ref('');
provide(messageDraftsKey, createMessageDrafts());
const viewportHeight = ref<number | null>(null);
const workspaceStyle = computed(() =>
    viewportHeight.value
        ? { '--messages-viewport': `${viewportHeight.value}px` }
        : {},
);
const sortedInbox = computed(() =>
    [...inbox.value].sort(
        (a, b) => b.updated_at.localeCompare(a.updated_at) || b.id - a.id,
    ),
);
let alive = true;
let inboxRefreshQueued = false;
const controller = new AbortController();
function recipient(conversation: DirectConversation) {
    return conversation.participants.find(
        (participant) => !participant.is_self,
    );
}
function preview(conversation: DirectConversation) {
    const message = conversation.latest_message;

    return message
        ? `${message.sender.is_self ? 'You' : message.sender.name}: ${message.body}`
        : 'Say hello to start your conversation';
}
async function loadInbox(more = false) {
    if (inboxLoading.value) {
        if (!more) {
            inboxRefreshQueued = true;
        }

        return;
    }

    inboxLoading.value = true;
    inboxError.value = '';

    try {
        const response = await messagingRequest<
            CursorPage<DirectConversation> & { unread_count: number }
        >(
            inboxRoute.url({
                query: {
                    cursor: more ? (inboxCursor.value ?? undefined) : undefined,
                },
            }),
            { signal: controller.signal },
        );

        if (!alive) {
            return;
        }

        const merged = new Map(
            inbox.value.map((conversation) => [conversation.id, conversation]),
        );
        response.data.forEach((conversation) =>
            merged.set(conversation.id, conversation),
        );
        inbox.value = [...merged.values()];
        // Refreshing the first page resets pagination, while retaining already-loaded conversations.
        inboxCursor.value = response.next_cursor;
        page.props.unreadMessagesCount = response.unread_count;
    } catch (failure) {
        if (alive) {
            inboxError.value = messageError(failure);
        }
    } finally {
        inboxLoading.value = false;

        if (inboxRefreshQueued && alive) {
            inboxRefreshQueued = false;
            void loadInbox();
        }
    }
}
function conversationChanged(id: number, read: boolean) {
    if (read) {
        const conversation = inbox.value.find((item) => item.id === id);

        if (conversation) {
            conversation.unread_count = 0;
        }
    }

    void loadInbox();
}
function openConversation(id: number) {
    activeModal.value = null;
    router.visit(showConversation.url(id), { preserveState: true });
    void loadInbox();
}
function resizeViewport() {
    viewportHeight.value = window.visualViewport?.height ?? null;
}
onMounted(() => {
    void loadInbox();
    resizeViewport();
    window.visualViewport?.addEventListener('resize', resizeViewport);
});
onBeforeUnmount(() => {
    alive = false;
    controller.abort();
    window.visualViewport?.removeEventListener('resize', resizeViewport);
});
</script>
<style scoped>
.messages-workspace {
    height: calc(
        var(--messages-viewport, 100dvh) - 13rem - env(safe-area-inset-bottom)
    );
    min-height: 25rem;
}
@media (max-width: 1023px) {
    .messages-workspace-open {
        height: calc(
            var(--messages-viewport, 100dvh) - 11rem -
                env(safe-area-inset-bottom)
        );
        min-height: 13rem;
    }
}
@media (min-width: 1024px) {
    .messages-workspace {
        height: calc(100dvh - 5rem);
        min-height: 30rem;
    }
}
</style>
