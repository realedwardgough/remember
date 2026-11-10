<template>
    <div class="grid gap-4">
        <label class="field-label"
            >Find a group<input
                v-model="search"
                type="search"
                class="field"
                placeholder="Search groups"
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
        <ul class="grid gap-1">
            <li v-for="group in filtered" :key="group.id">
                <button
                    type="button"
                    class="flex min-h-16 w-full items-center gap-3 rounded-xl p-3 text-left hover:bg-lilac"
                    :disabled="!group.can_send"
                    @click="emit('select', group)"
                >
                    <Users :size="22" class="shrink-0 text-primary" />
                    <span class="min-w-0"
                        ><span class="block truncate font-semibold">{{
                            conversationName(group)
                        }}</span
                        ><span class="text-xs text-stone-500"
                            >{{ group.participants.length }} members</span
                        ></span
                    >
                </button>
            </li>
        </ul>
        <p
            v-if="!loading && !filtered.length"
            class="py-4 text-center text-sm text-stone-500"
        >
            {{
                search
                    ? 'No matching groups in this list.'
                    : 'Create a group in Messages to share posts with it.'
            }}
        </p>
        <button
            v-if="cursor || loading"
            type="button"
            class="button-secondary"
            :disabled="loading"
            @click="load"
        >
            {{ loading ? 'Loading groups…' : 'More groups' }}
        </button>
    </div>
</template>
<script setup lang="ts">
import { Users } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { conversationName } from '@/lib/conversations';
import { messageError, messagingRequest } from '@/lib/messaging';
import { index } from '@/routes/messaging';
import type { CursorPage, DirectConversation } from '@/types/messaging';
const emit = defineEmits<{ select: [conversation: DirectConversation] }>();
const groups = ref<DirectConversation[]>([]);
const search = ref('');
const cursor = ref<string | null>(null);
const loading = ref(false);
const error = ref('');
const controller = new AbortController();
let alive = true;
const filtered = computed(() =>
    groups.value.filter((group) =>
        conversationName(group)
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
        const response = await messagingRequest<CursorPage<DirectConversation>>(
            index.url({
                query: { groups_only: 1, cursor: cursor.value ?? undefined },
            }),
            { signal: controller.signal },
        );

        if (alive) {
            const unique = new Map(
                groups.value.map((group) => [group.id, group]),
            );
            response.data.forEach((group) => unique.set(group.id, group));
            groups.value = [...unique.values()];
            cursor.value = response.next_cursor;
        }
    } catch (failure) {
        if (alive) {
            error.value = messageError(failure);
        }
    } finally {
        loading.value = false;
    }
}
onMounted(load);
onBeforeUnmount(() => {
    alive = false;
    controller.abort();
});
</script>
