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
                <p
                    v-if="notice"
                    role="alert"
                    class="absolute inset-x-4 top-20 z-10 rounded-xl bg-black/70 p-3 text-sm"
                >
                    {{ notice }}
                </p>
                <div
                    class="flex h-full w-full touch-pan-y touch-pinch-zoom items-center justify-center"
                    @touchstart.passive="startSwipe"
                    @touchend.passive="endSwipe"
                    @touchcancel="swipeStart = null"
                >
                    <img
                        v-if="image"
                        :key="image.id"
                        :src="image.url"
                        :alt="image.name"
                        class="max-h-full w-full object-contain"
                        draggable="false"
                    />
                </div>
                <div
                    v-if="hasMultipleImages"
                    class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-5 bg-gradient-to-t from-black/60 to-transparent px-3 pt-8 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
                >
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-full bg-black/30 disabled:opacity-30"
                        aria-label="Previous photo"
                        :disabled="currentIndex <= 0"
                        @click="navigate(-1)"
                    >
                        <ChevronLeft :size="22" />
                    </button>
                    <p class="text-sm" aria-live="polite" aria-atomic="true">
                        {{ currentIndex + 1 }} of {{ images.length }}
                    </p>
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-full bg-black/30 disabled:opacity-30"
                        aria-label="Next photo"
                        :disabled="currentIndex >= images.length - 1"
                        @click="navigate(1)"
                    >
                        <ChevronRight :size="22" />
                    </button>
                </div>
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
            <p v-if="notice" role="alert" class="text-sm text-stone-600">
                {{ notice }}
            </p>
            <div
                class="touch-pan-y touch-pinch-zoom"
                @touchstart.passive="startSwipe"
                @touchend.passive="endSwipe"
                @touchcancel="swipeStart = null"
            >
                <img
                    :key="image.id"
                    :src="image.url"
                    :alt="image.name"
                    class="mx-auto max-h-[60dvh] max-w-full rounded-xl object-contain"
                    draggable="false"
                />
            </div>
            <div
                v-if="hasMultipleImages"
                class="flex items-center justify-center gap-4"
            >
                <button
                    type="button"
                    class="button-secondary disabled:opacity-30"
                    aria-label="Previous photo"
                    :disabled="currentIndex <= 0"
                    @click="navigate(-1)"
                >
                    <ChevronLeft :size="20" />Previous
                </button>
                <p
                    class="text-sm text-stone-500"
                    aria-live="polite"
                    aria-atomic="true"
                >
                    {{ currentIndex + 1 }} of {{ images.length }}
                </p>
                <button
                    type="button"
                    class="button-secondary disabled:opacity-30"
                    aria-label="Next photo"
                    :disabled="currentIndex >= images.length - 1"
                    @click="navigate(1)"
                >
                    Next<ChevronRight :size="20" />
                </button>
            </div>
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
import { useEventListener, useMediaQuery } from '@vueuse/core';
import { ChevronLeft, ChevronRight, Download, X } from 'lucide-vue-next';
import {
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, watch } from 'vue';
import Modal from '@/components/Modal.vue';
import type { ImageViewerMedia } from '@/types';
const props = defineProps<{
    image: ImageViewerMedia | null;
    images?: ImageViewerMedia[];
    title?: string | null;
    subtitle?: string | null;
    notice?: string;
}>();
const images = computed(
    () => props.images ?? (props.image ? [props.image] : []),
);
const currentIndex = computed(() =>
    images.value.findIndex((image) => image.id === props.image?.id),
);
const hasMultipleImages = computed(
    () => currentIndex.value >= 0 && images.value.length > 1,
);
const navigate = (direction: number) => {
    if (!hasMultipleImages.value) {
        return;
    }

    const image = images.value[currentIndex.value + direction];

    if (image) {
        emit('select', image);
    }
};
let swipeStart: { x: number; y: number } | null = null;
const startSwipe = (event: TouchEvent) => {
    const touch = event.touches[0];
    swipeStart =
        event.touches.length === 1 && touch
            ? { x: touch.clientX, y: touch.clientY }
            : null;
};
const endSwipe = (event: TouchEvent) => {
    const start = swipeStart;
    swipeStart = null;
    const touch = event.changedTouches[0];

    if (!start || !touch || event.touches.length > 0) {
        return;
    }

    const deltaX = touch.clientX - start.x;
    const deltaY = touch.clientY - start.y;

    if (Math.abs(deltaX) >= 50 && Math.abs(deltaX) > Math.abs(deltaY) * 1.5) {
        navigate(deltaX < 0 ? 1 : -1);
    }
};
useEventListener('keydown', (event: KeyboardEvent) => {
    if (
        !props.image ||
        !hasMultipleImages.value ||
        event.altKey ||
        event.ctrlKey ||
        event.metaKey
    ) {
        return;
    }

    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        navigate(event.key === 'ArrowLeft' ? -1 : 1);
    }
});
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
const emit = defineEmits<{ close: []; select: [image: ImageViewerMedia] }>();
</script>
