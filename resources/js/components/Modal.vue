<template>
    <DialogRoot :open="show" @update:open="onOpenChange">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-stone-950/35 backdrop-blur-sm"
            />
            <DialogContent
                :aria-describedby="undefined"
                class="fixed z-50 flex flex-col overflow-y-auto bg-canvas shadow-xl sm:top-1/2 sm:left-1/2 sm:max-h-[90dvh] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-3xl"
                :class="[
                    wide ? 'sm:max-w-6xl' : 'sm:max-w-2xl',
                    mobileFullscreen
                        ? 'inset-0 h-dvh w-full sm:inset-auto sm:h-auto sm:w-[calc(100%-4rem)]'
                        : 'top-1/2 left-1/2 max-h-[90dvh] w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 rounded-3xl',
                ]"
                @close-auto-focus.prevent="restoreFocus"
                @escape-key-down.prevent="requestClose"
                @pointer-down-outside.prevent="requestClose"
                @interact-outside.prevent
            >
                <header
                    class="sticky top-0 z-10 flex shrink-0 items-center justify-between gap-4 border-b bg-canvas/95 px-5 pt-[max(1.25rem,env(safe-area-inset-top))] pb-4 backdrop-blur sm:px-7"
                >
                    <div>
                        <p
                            v-if="eyebrow"
                            class="mb-1 text-xs font-medium text-primary"
                        >
                            {{ eyebrow }}
                        </p>
                        <DialogTitle class="story-font text-2xl">{{
                            title
                        }}</DialogTitle>
                    </div>
                    <button
                        type="button"
                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-white text-stone-600 hover:bg-lilac"
                        aria-label="Close dialog"
                        :disabled="state.busy || busy"
                        @click="requestClose"
                    >
                        <X :size="20" />
                    </button>
                </header>
                <div
                    v-if="confirmDiscard"
                    ref="discardPrompt"
                    role="alert"
                    class="mx-5 mt-4 rounded-2xl border border-rose-200 bg-blush p-4"
                >
                    <p class="font-semibold">Discard this unfinished memory?</p>
                    <p class="mt-1 text-sm text-stone-600">
                        Your writing and selected photos will be lost.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            class="button-primary"
                            @click="confirmDiscard = false"
                        >
                            Keep writing
                        </button>
                        <button
                            class="button-secondary"
                            :disabled="state.busy || busy"
                            @click="emit('close')"
                        >
                            Discard memory
                        </button>
                    </div>
                </div>
                <div
                    class="px-5 pt-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:px-7 sm:pt-6"
                >
                    <slot />
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
<script setup lang="ts">
import { X } from 'lucide-vue-next';
import {
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { nextTick, provide, reactive, ref, watch } from 'vue';
import { dialogStateKey } from '@/composables/useDialogState';
const props = defineProps<{
    show: boolean;
    title: string;
    eyebrow?: string;
    mobileFullscreen?: boolean;
    wide?: boolean;
    busy?: boolean;
}>();
const emit = defineEmits<{ close: [] }>();
const state = reactive({ dirty: false, busy: false });
provide(dialogStateKey, state);
const confirmDiscard = ref(false);
const discardPrompt = ref<HTMLElement | null>(null);
let previouslyFocused: HTMLElement | null = null;
const restoreFocus = () => {
    previouslyFocused?.focus();
};
watch(
    () => props.show,
    (show) => {
        if (show && typeof document !== 'undefined') {
            previouslyFocused = document.activeElement as HTMLElement;
        }

        confirmDiscard.value = false;
        state.dirty = false;
        state.busy = false;
    },
);
const requestClose = () => {
    if (state.busy || props.busy) {
        return;
    }

    if (state.dirty) {
        confirmDiscard.value = true;
        nextTick(() => {
            discardPrompt.value?.querySelector('button')?.focus();
        });
    } else {
        emit('close');
    }
};
const onOpenChange = (open: boolean) => {
    if (!open) {
        requestClose();
    }
};
</script>
