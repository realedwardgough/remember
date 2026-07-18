<template>
    <div
        v-if="post.images.length > 0 || post.files.length > 0"
        class="mt-3 grid gap-3"
    >
        <div v-if="post.images.length > 0" class="grid gap-2">
            <template v-if="post.images.length === 1">
                <div class="grid grid-cols-1 gap-2">
                    <button
                        v-for="image in visibleImages"
                        :key="image.id"
                        type="button"
                        class="group relative aspect-square cursor-zoom-in overflow-hidden rounded-md border border-slate-200 bg-slate-100 sm:w-[50%]"
                        @click="openImage(image)"
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
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <button
                        v-for="image in visibleImages"
                        :key="image.id"
                        type="button"
                        class="group relative aspect-square cursor-zoom-in overflow-hidden rounded-md border border-slate-200 bg-slate-100"
                        @click="openImage(image)"
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
                class="justify-self-start rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
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
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
            >
                <span class="max-w-44 truncate">{{ file.name }}</span>
                <span class="text-slate-400">Download</span>
            </a>
        </div>
    </div>

    <ImageViewer :image="selectedImage" @close="closeImage" />
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import ImageViewer from '@/components/ImageViewer.vue';
import type { TimelineMedia, TimelinePost } from '@/types';

const props = defineProps<{
    post: TimelinePost;
}>();

const imagePreviewLimit = 5;
const showAllImages = ref(false);
const selectedImage = ref<TimelineMedia | null>(null);

const visibleImages = computed(() => {
    if (showAllImages.value) {
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
