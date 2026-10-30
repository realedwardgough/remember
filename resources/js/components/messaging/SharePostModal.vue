<template>
    <Modal
        :show="true"
        title="Send in a message"
        eyebrow="Share a little moment"
        :busy="processing"
        @close="emit('close')"
    >
        <div class="mb-5 rounded-2xl border bg-white p-4">
            <p class="text-xs text-stone-500">
                {{ post.type }} · {{ post.date }}
            </p>
            <p class="mt-1 font-semibold [overflow-wrap:anywhere]">
                {{ post.title }}
            </p>
        </div>
        <ContactPicker v-if="!selected" select-only @select="selectRecipient" />
        <form v-else class="grid gap-4" @submit.prevent="send">
            <div class="flex items-center justify-between gap-3">
                <p class="min-w-0 text-sm">
                    To <strong>{{ selected.name }}</strong>
                </p>
                <button
                    type="button"
                    class="min-h-11 text-sm text-primary underline"
                    :disabled="processing || attempt !== null"
                    @click="selected = null"
                >
                    Choose someone else
                </button>
            </div>
            <label class="field-label"
                >Add a note
                <span class="text-xs font-normal text-stone-500">Optional</span
                ><textarea
                    v-model="note"
                    rows="3"
                    maxlength="20000"
                    class="field resize-y"
                    :disabled="processing || attempt !== null"
                    placeholder="Thought you’d like to see this…"
                />
            </label>
            <p v-if="tooLong" role="alert" class="text-sm text-red-700">
                Your note can contain up to 10,000 characters.
            </p>
            <p
                v-if="error"
                role="alert"
                class="rounded-xl bg-blush p-3 text-sm text-red-800"
            >
                {{ error }}
            </p>
            <button
                type="submit"
                class="button-primary"
                :disabled="processing || tooLong"
            >
                <Send :size="17" />{{
                    processing
                        ? 'Sending…'
                        : attempt
                          ? 'Retry sending'
                          : 'Send in message'
                }}
            </button>
        </form>
    </Modal>
</template>
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Send } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ContactPicker from '@/components/messaging/ContactPicker.vue';
import Modal from '@/components/Modal.vue';
import {
    MessagingRequestError,
    messageError,
    messagingRequest,
} from '@/lib/messaging';
import { show as showConversation } from '@/routes/messages';
import { sharePost } from '@/routes/messaging';
import type { TimelinePost } from '@/types';
import type { DirectMessage, MessagingContact } from '@/types/messaging';
const props = defineProps<{ post: TimelinePost }>();
const emit = defineEmits<{ close: [] }>();
const selected = ref<MessagingContact | null>(null);
const note = ref('');
const processing = ref(false);
const error = ref('');
const tooLong = computed(() => [...note.value].length > 10000);
const attempt = ref<{
    recipient_id: number;
    body: string;
    client_request_id: string;
} | null>(null);
function selectRecipient(contact: MessagingContact) {
    selected.value = contact;
    error.value = '';
}
async function send() {
    if (processing.value || !selected.value || tooLong.value) {
        return;
    }

    attempt.value ??= {
        recipient_id: selected.value.id,
        body: note.value.trim(),
        client_request_id: crypto.randomUUID(),
    };
    processing.value = true;
    error.value = '';

    try {
        const response = await messagingRequest<{ data: DirectMessage }>(
            sharePost.url(props.post.id),
            { method: 'POST', body: attempt.value },
        );
        emit('close');
        router.visit(showConversation.url(response.data.conversation_id));
    } catch (failure) {
        error.value =
            failure instanceof MessagingRequestError &&
            [403, 404].includes(failure.status)
                ? 'This post or family member is no longer available for sharing.'
                : messageError(failure);

        // Keep an uncertain send's exact body and UUID for retry; a known rejection can be edited.
        if (
            failure instanceof MessagingRequestError &&
            failure.status >= 400 &&
            failure.status < 500
        ) {
            attempt.value = null;
        }
    } finally {
        processing.value = false;
    }
}
</script>
