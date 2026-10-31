<template>
    <DialogRoot
        v-if="isMobile"
        :open="image !== null"
        @update:open="!$event && emit('close')"
    >
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-50 bg-black" />
            <DialogContent
                :aria-describedby="undefined"
                class="fixed inset-0 z-50 flex h-dvh w-full items-center justify-center bg-black text-white outline-none"
                @close-auto-focus.prevent="restoreFocus"
            >
                <DialogTitle class="sr-only">{{
                    title || image?.name || 'Photo'
                }}</DialogTitle>
                <img
                    v-if="image"
                    :src="image.url"
                    :alt="image.name"
                    class="max-h-full w-full object-contain"
                />
                <div
                    class="absolute inset-x-0 top-0 flex items-center justify-between gap-3 bg-gradient-to-b from-black/60 to-transparent px-3 pt-[max(0.75rem,env(safe-area-inset-top))] pb-8"
                >
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-full bg-black/30 text-white/90"
                        aria-label="Close photo"
                        @click="emit('close')"
                    >
                        <X :size="22" />
                    </button>
                    <a
                        v-if="image"
                        :href="image.downloadUrl"
                        class="flex size-11 items-center justify-center rounded-full bg-black/30 text-white/80"
                        :aria-label="`Download ${image.name}`"
                        title="Download photo"
                        ><Download :size="19"
                    /></a>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
    <Modal
        v-else
        :show="image !== null"
        :title="title || image?.name || 'Photo'"
        eyebrow="A closer look"
        wide
        @close="emit('close')"
    >
        <div v-if="image" class="grid gap-4">
            <img
                :src="image.url"
                :alt="image.name"
                class="mx-auto max-h-[60dvh] max-w-full rounded-xl object-contain"
            />
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p
                    class="min-w-0 flex-1 text-xs leading-5 break-words text-stone-500"
                >
                    {{ image.name
                    }}<span v-if="subtitle"> · {{ subtitle }}</span
                    ><span v-if="image.width && image.height">
                        · {{ image.width }} × {{ image.height }}</span
                    >
                </p>
                <a :href="image.downloadUrl" class="button-secondary"
                    ><Download :size="17" />Download</a
                >
            </div>
        </div>
    </Modal>
</template>
<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { Download, X } from 'lucide-vue-next';
import {
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { watch } from 'vue';
import Modal from '@/components/Modal.vue';
import type { ImageViewerMedia } from '@/types';
const props = defineProps<{
    image: ImageViewerMedia | null;
    title?: string | null;
    subtitle?: string | null;
}>();
const isMobile = useMediaQuery('(max-width: 1023px)');
let previouslyFocused: HTMLElement | null = null;
watch(
    () => props.image,
    (image) => {
        if (image && !previouslyFocused) {
            previouslyFocused = document.activeElement as HTMLElement;
        }
    },
);
const restoreFocus = () => {
    previouslyFocused?.focus();
    previouslyFocused = null;
};
const emit = defineEmits<{ close: [] }>();
</script>
