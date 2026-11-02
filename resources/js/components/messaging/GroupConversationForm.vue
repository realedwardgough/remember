<template>
    <form class="grid gap-4" @submit.prevent="createGroup">
        <fieldset
            class="grid min-w-0 gap-4"
            :disabled="processing || attempt !== null"
        >
            <label class="field-label">
                Group name
                <input
                    v-model="name"
                    class="field"
                    maxlength="80"
                    required
                    placeholder="e.g. Weekend plans"
                />
            </label>
            <div class="grid gap-2">
                <p class="text-sm text-stone-500">
                    Choose two to nineteen family members. The member list is
                    fixed when you create the group.
                </p>
                <p
                    class="text-xs font-semibold text-primary"
                    aria-live="polite"
                >
                    You + {{ selected.length }} selected
                </p>
                <ul
                    v-if="selected.length"
                    class="flex flex-wrap gap-2"
                    aria-label="Selected group members"
                >
                    <li v-for="contact in selected" :key="contact.id">
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-lilac px-3 text-sm"
                            :aria-label="`Remove ${contact.name} from selection`"
                            @click="toggle(contact)"
                        >
                            {{ contact.name
                            }}<X :size="14" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
            </div>
            <ContactPicker
                select-only
                :selected-ids="selected.map((person) => person.id)"
                @select="toggle"
            />
        </fieldset>
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
            :disabled="processing || selected.length < 2 || !name.trim()"
        >
            <Users :size="18" />{{
                processing
                    ? 'Creating group…'
                    : attempt
                      ? 'Retry creating group'
                      : 'Create group'
            }}
        </button>
    </form>
</template>
<script setup lang="ts">
import { Users, X } from 'lucide-vue-next';
import { onBeforeUnmount, ref } from 'vue';
import ContactPicker from '@/components/messaging/ContactPicker.vue';
import {
    MessagingRequestError,
    messageError,
    messagingRequest,
} from '@/lib/messaging';
import { store } from '@/routes/messaging';
import type { DirectConversation, MessagingContact } from '@/types/messaging';

const emit = defineEmits<{ open: [id: number] }>();
const name = ref('');
const selected = ref<MessagingContact[]>([]);
const processing = ref(false);
const error = ref('');
const attempt = ref<{
    name: string;
    recipient_ids: number[];
    client_request_id: string;
} | null>(null);
const controller = new AbortController();
let alive = true;

function toggle(contact: MessagingContact) {
    if (selected.value.some((person) => person.id === contact.id)) {
        selected.value = selected.value.filter(
            (person) => person.id !== contact.id,
        );
    } else if (selected.value.length < 19) {
        selected.value.push(contact);
    } else {
        error.value = 'A group can include up to twenty people, including you.';
    }
}
async function createGroup() {
    if (processing.value || selected.value.length < 2 || !name.value.trim()) {
        return;
    }

    attempt.value ??= {
        name: name.value.trim(),
        recipient_ids: selected.value.map((person) => person.id),
        client_request_id: crypto.randomUUID(),
    };
    processing.value = true;
    error.value = '';

    try {
        const response = await messagingRequest<{ data: DirectConversation }>(
            store.url(),
            {
                method: 'POST',
                body: attempt.value,
                signal: controller.signal,
            },
        );

        if (alive) {
            emit('open', response.data.id);
        }
    } catch (failure) {
        if (alive) {
            error.value = messageError(failure);

            if (
                failure instanceof MessagingRequestError &&
                failure.status >= 400 &&
                failure.status < 500
            ) {
                attempt.value = null;
            }
        }
    } finally {
        processing.value = false;
    }
}
onBeforeUnmount(() => {
    alive = false;
    controller.abort();
});
</script>
