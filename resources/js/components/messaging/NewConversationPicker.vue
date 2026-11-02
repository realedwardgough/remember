<template>
    <div class="grid gap-5">
        <div class="flex gap-2" aria-label="Conversation type">
            <button
                type="button"
                class="type-chip"
                :class="
                    kind === 'direct'
                        ? 'bg-primary text-white'
                        : 'bg-lilac text-primary'
                "
                :aria-pressed="kind === 'direct'"
                @click="kind = 'direct'"
            >
                One to one
            </button>
            <button
                type="button"
                class="type-chip"
                :class="
                    kind === 'group'
                        ? 'bg-primary text-white'
                        : 'bg-lilac text-primary'
                "
                :aria-pressed="kind === 'group'"
                @click="kind = 'group'"
            >
                New group
            </button>
        </div>
        <ContactPicker v-if="kind === 'direct'" @open="emit('open', $event)" />
        <GroupConversationForm v-else @open="emit('open', $event)" />
    </div>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import ContactPicker from '@/components/messaging/ContactPicker.vue';
import GroupConversationForm from '@/components/messaging/GroupConversationForm.vue';
const kind = ref<'direct' | 'group'>('direct');
const emit = defineEmits<{ open: [id: number] }>();
</script>
