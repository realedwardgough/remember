<template>
    <form class="border-t bg-white p-3 sm:p-4" @submit.prevent="submit">
        <label for="message-body" class="sr-only">Message</label>
        <div class="flex items-end gap-2 rounded-2xl border bg-canvas p-2">
            <EmojiPicker
                :disabled="disabled"
                @select="insertEmoji"
                @close="input?.focus()"
            />
            <textarea
                id="message-body"
                ref="input"
                :value="modelValue"
                :disabled="disabled"
                rows="2"
                maxlength="20000"
                placeholder="Write a message…"
                class="max-h-40 min-h-12 min-w-0 flex-1 resize-y border-0 bg-transparent py-2.5 text-base outline-none"
                :aria-invalid="tooLong || undefined"
                :aria-describedby="tooLong ? 'message-length-error' : undefined"
                @input="update"
                @blur="emit('blur')"
                @keydown="keydown"
            />
            <button
                type="submit"
                :disabled="disabled || !modelValue.trim() || tooLong"
                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-white"
                aria-label="Send message"
            >
                <Send :size="19" />
            </button>
        </div>
        <p
            v-if="tooLong"
            id="message-length-error"
            class="mt-2 text-sm text-red-700"
            role="alert"
        >
            Messages can contain up to 10,000 characters.
        </p>
        <p v-else class="mt-2 text-xs text-stone-500">
            A little note, just between you two.
        </p>
    </form>
</template>
<script setup lang="ts">
import { Send } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import EmojiPicker from '@/components/messaging/EmojiPicker.vue';
const props = defineProps<{ modelValue: string; disabled?: boolean }>();
const emit = defineEmits<{
    'update:modelValue': [value: string];
    send: [];
    blur: [];
}>();
const input = ref<HTMLTextAreaElement | null>(null);
const tooLong = computed(() => [...props.modelValue].length > 10000);
function update(event: Event) {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
}
function submit() {
    if (props.disabled || !props.modelValue.trim() || tooLong.value) {
        return;
    }

    emit('send');
}
function keydown(event: KeyboardEvent) {
    if (
        event.key === 'Enter' &&
        (event.metaKey || event.ctrlKey) &&
        !event.isComposing
    ) {
        event.preventDefault();
        submit();
    }
}
async function insertEmoji(emoji: string) {
    const start = input.value?.selectionStart ?? props.modelValue.length;
    const end = input.value?.selectionEnd ?? start;
    emit(
        'update:modelValue',
        props.modelValue.slice(0, start) + emoji + props.modelValue.slice(end),
    );
    await nextTick();
    input.value?.focus();
    input.value?.setSelectionRange(start + emoji.length, start + emoji.length);
}
</script>
