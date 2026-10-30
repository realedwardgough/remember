<template>
    <div class="grid gap-4">
        <label class="field-label"
            >Find a family member<input
                v-model="search"
                type="search"
                class="field"
                placeholder="Search by name or username"
                autocomplete="off"
        /></label>
        <p
            v-if="error"
            role="alert"
            class="rounded-xl bg-blush p-3 text-sm text-red-800"
        >
            {{ error }}
            <button type="button" class="underline" @click="load">
                Try again
            </button>
        </p>
        <p v-if="starting" class="text-sm text-stone-500" role="status">
            Opening your conversation…
        </p>
        <ul class="grid gap-1">
            <li v-for="contact in filtered" :key="contact.id">
                <button
                    type="button"
                    :disabled="starting"
                    class="flex min-h-16 w-full items-center gap-3 rounded-xl p-3 text-left hover:bg-lilac"
                    @click="start(contact)"
                >
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-mint font-semibold"
                        aria-hidden="true"
                        >{{ contact.name.slice(0, 1) }}</span
                    >
                    <span class="min-w-0"
                        ><span class="block truncate font-semibold">{{
                            contact.name
                        }}</span
                        ><span class="block truncate text-xs text-stone-500"
                            >@{{ contact.username }}</span
                        ></span
                    >
                    <ArrowRight
                        :size="18"
                        class="ml-auto shrink-0 text-stone-400"
                    />
                </button>
            </li>
        </ul>
        <p
            v-if="!loading && !filtered.length"
            class="py-4 text-center text-sm text-stone-500"
        >
            {{
                search
                    ? 'No matching family members in this list.'
                    : 'Other family members will appear here once they join Remember.'
            }}
        </p>
        <button
            v-if="cursor || loading"
            type="button"
            class="button-secondary"
            :disabled="loading"
            @click="load"
        >
            {{ loading ? 'Loading family members…' : 'More family members' }}
        </button>
    </div>
</template>
<script setup lang="ts">
import { ArrowRight } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { messageError, messagingRequest } from '@/lib/messaging';
import { contacts as contactsRoute, store } from '@/routes/messaging';
import type {
    CursorPage,
    DirectConversation,
    MessagingContact,
} from '@/types/messaging';
const emit = defineEmits<{ open: [id: number] }>();
const contacts = ref<MessagingContact[]>([]);
const cursor = ref<string | null>(null);
const loading = ref(false);
const starting = ref(false);
const error = ref('');
const search = ref('');
const controller = new AbortController();
let alive = true;
const filtered = computed(() =>
    contacts.value.filter((contact) =>
        `${contact.name} ${contact.username}`
            .toLocaleLowerCase()
            .includes(search.value.toLocaleLowerCase()),
    ),
);
async function load() {
    if (loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const response = await messagingRequest<CursorPage<MessagingContact>>(
            contactsRoute.url({ query: { cursor: cursor.value ?? undefined } }),
            { signal: controller.signal },
        );

        if (!alive) {
            return;
        }

        const unique = new Map(
            contacts.value.map((contact) => [contact.id, contact]),
        );
        response.data.forEach((contact) => unique.set(contact.id, contact));
        contacts.value = [...unique.values()];
        cursor.value = response.next_cursor;
    } catch (failure) {
        if (alive) {
            error.value = messageError(failure);
        }
    } finally {
        loading.value = false;
    }
}
async function start(contact: MessagingContact) {
    if (starting.value) {
        return;
    }

    starting.value = true;
    error.value = '';

    try {
        const response = await messagingRequest<{ data: DirectConversation }>(
            store.url(),
            { method: 'POST', body: { recipient_id: contact.id } },
        );

        if (alive) {
            emit('open', response.data.id);
        }
    } catch (failure) {
        if (alive) {
            error.value = messageError(failure);
        }
    } finally {
        starting.value = false;
    }
}
onMounted(load);
onBeforeUnmount(() => {
    alive = false;
    controller.abort();
});
</script>
