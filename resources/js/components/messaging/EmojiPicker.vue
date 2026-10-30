<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger as-child>
            <button
                type="button"
                class="flex size-11 shrink-0 items-center justify-center rounded-xl text-stone-500 hover:bg-lilac"
                aria-label="Choose an emoji"
                :disabled="disabled"
            >
                <Smile :size="21" />
            </button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                side="top"
                :side-offset="8"
                class="z-50 w-64 rounded-2xl border bg-white p-3 shadow-lg"
                aria-label="Choose an emoji"
                @close-auto-focus.prevent="emit('close')"
            >
                <p class="mb-2 text-xs font-semibold text-stone-500">
                    A little expression
                </p>
                <div class="grid grid-cols-5 gap-1">
                    <button
                        v-for="emoji in emojis"
                        :key="emoji.symbol"
                        type="button"
                        :aria-label="emoji.label"
                        class="flex size-11 items-center justify-center rounded-xl text-2xl hover:bg-lilac"
                        @click="choose(emoji.symbol)"
                    >
                        {{ emoji.symbol }}
                    </button>
                </div>
                <p class="mt-2 text-xs text-stone-500">
                    Your keyboard has even more.
                </p>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
<script setup lang="ts">
import { Smile } from 'lucide-vue-next';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { ref } from 'vue';
defineProps<{ disabled?: boolean }>();
const emit = defineEmits<{ select: [emoji: string]; close: [] }>();
const open = ref(false);
const emojis = [
    { symbol: '❤️', label: 'Red heart' },
    { symbol: '😊', label: 'Smiling face' },
    { symbol: '😂', label: 'Tears of joy' },
    { symbol: '🥰', label: 'Smiling face with hearts' },
    { symbol: '😘', label: 'Blowing a kiss' },
    { symbol: '👍', label: 'Thumbs up' },
    { symbol: '👋', label: 'Waving hand' },
    { symbol: '🎉', label: 'Celebration' },
    { symbol: '💕', label: 'Two hearts' },
    { symbol: '🤗', label: 'Hugging face' },
    { symbol: '😍', label: 'Heart eyes' },
    { symbol: '🙏', label: 'Folded hands' },
    { symbol: '✨', label: 'Sparkles' },
    { symbol: '🥳', label: 'Party face' },
    { symbol: '🌸', label: 'Cherry blossom' },
    { symbol: '😢', label: 'Crying face' },
    { symbol: '😮', label: 'Surprised face' },
    { symbol: '💪', label: 'Flexed arm' },
    { symbol: '☀️', label: 'Sunshine' },
    { symbol: '🫶', label: 'Heart hands' },
];
function choose(emoji: string) {
    emit('select', emoji);
    open.value = false;
}
</script>
