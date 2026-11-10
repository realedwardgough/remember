<template>
    <div
        v-if="post.images.length > 0 || post.files.length > 0"
        class="mt-3 grid gap-3"
        @touchmove.passive="handleTouchMove"
        @touchcancel="cancelTap"
    >
        <div v-if="post.images.length > 0" class="grid gap-2">
            <template v-if="post.images.length === 1">
                <div class="grid grid-cols-1 gap-2">
                    <button
                        v-for="image in visibleImages"
                        :key="image.id"
                        type="button"
                        class="group relative aspect-[4/3] w-full cursor-zoom-in overflow-hidden rounded-xl border border-slate-200 bg-slate-100 sm:aspect-[16/9] sm:max-h-96"
                        @touchstart.passive="handleTouchStart"
                        @touchend="
                            handleTouchEnd($event, () => openImage(image))
                        "
                        @click.stop="handleTap($event, () => openImage(image))"
                    >
                        <img
                            :src="image.url"
                            :alt="image.name"
                            loading="lazy"
                            class="border-overlay size-full w-full object-cover transition group-hover:scale-105"
                        />
                        <span class="sr-only">Open {{ image.name }}</span>
                    </button>
                </div>
            </template>
            <template v-else>
                <div
                    class="flex snap-x snap-mandatory gap-2 overflow-x-auto overscroll-x-contain pb-2 lg:grid lg:grid-cols-3 lg:overflow-visible lg:pb-0"
                    aria-label="Post photos"
                    tabindex="0"
                >
                    <button
                        v-for="image in visibleImages"
                        :key="image.id"
                        type="button"
                        class="group relative aspect-[4/3] w-[80%] shrink-0 cursor-zoom-in snap-center overflow-hidden rounded-xl border border-slate-200 bg-slate-100 lg:w-auto"
                        @touchstart.passive="handleTouchStart"
                        @touchend="
                            handleTouchEnd($event, () => openImage(image))
                        "
                        @click.stop="handleTap($event, () => openImage(image))"
                    >
                        <img
                            :src="image.url"
                            :alt="image.name"
                            loading="lazy"
                            class="border-overlay size-full object-cover transition group-hover:scale-105"
                        />
                        <span class="sr-only">Open {{ image.name }}</span>
                    </button>
                </div>
            </template>
            <button
                v-if="post.images.length > imagePreviewLimit"
                type="button"
                class="hidden min-h-11 justify-self-start rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 lg:block"
                @click="showAllImages = !showAllImages"
            >
                {{
                    showAllImages
                        ? 'Show fewer images'
                        : `Show all ${post.images.length} images`
                }}
            </button>
        </div>

        <div v-if="post.files.length > 0" class="flex flex-wrap gap-2">
            <a
                v-for="file in post.files"
                :key="file.id"
                :href="file.downloadUrl"
                class="inline-flex min-h-11 items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
            >
                <span class="max-w-44 truncate">{{ file.name }}</span>
                <span class="text-slate-400">Download</span>
            </a>
        </div>
    </div>

    <ImageViewer
        :image="selectedImage"
        :images="post.images"
        @select="selectedImage = $event"
        @close="closeImage"
    />
</template>

<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { computed, ref } from 'vue';
import ImageViewer from '@/components/ImageViewer.vue';
import { useDoubleTap } from '@/composables/useDoubleTap';
import type { TapPosition } from '@/composables/useDoubleTap';
import type { TimelineMedia, TimelinePost } from '@/types';

const props = defineProps<{
    post: TimelinePost;
}>();

const emit = defineEmits<{ doubleTap: [event: TapPosition] }>();
const isMobile = useMediaQuery('(max-width: 1023px)');
const {
    handleTap,
    handleTouchStart,
    handleTouchMove,
    handleTouchEnd,
    cancelTap,
} = useDoubleTap({
    enabled: () => isMobile.value && props.post.type !== 'Letter',
    onDoubleTap: (event) => emit('doubleTap', event),
});
const imagePreviewLimit = 5;
const showAllImages = ref(false);
const selectedImage = ref<TimelineMedia | null>(null);

const visibleImages = computed(() => {
    if (isMobile.value || showAllImages.value) {
        return props.post.images;
    }

    return props.post.images.slice(0, imagePreviewLimit);
});

const openImage = (image: TimelineMedia) => {
    selectedImage.value = image;
};

const closeImage = () => {
    selectedImage.value = null;
};
</script>
