<template>
    <BaseLayout>
        <template #sidebar>
            <Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
            />
        </template>

        <Head title="Gallery" />

        <section class="grid gap-3 lg:gap-6">
            <header class="flex items-center justify-between gap-3 lg:hidden">
                <h1 class="text-lg font-semibold">Photos</h1>
                <span class="text-xs text-stone-500"
                    >{{ images.total }}
                    {{ images.total === 1 ? 'photo' : 'photos' }}</span
                >
            </header>
            <PageHeader
                class="hidden lg:flex"
                title="A little look back"
                eyebrow="Family photos"
                description="All your captured moments, together in one place."
            >
                <span
                    class="rounded-full bg-mint px-4 py-2 text-sm text-stone-600"
                    >{{ images.total }}
                    {{ images.total === 1 ? 'photo' : 'photos' }}</span
                >
            </PageHeader>

            <div
                v-if="images.total === 0"
                class="rounded-xl border border-stone-200 bg-white p-7 text-center sm:p-10"
            >
                <div
                    class="mx-auto flex size-16 items-center justify-center rounded-xl border border-stone-200 bg-primary text-2xl text-white"
                    aria-hidden="true"
                >
                    <AppIcon name="fa-solid fa-images" />
                </div>
                <h2 class="mt-6 text-2xl font-semibold text-zinc-950">
                    Your gallery is ready
                </h2>
                <p
                    class="mx-auto mt-2 max-w-md text-sm leading-6 font-medium text-zinc-700"
                >
                    Create a memory with photos and they will appear here, ready
                    to revisit whenever you like.
                </p>
                <button
                    type="button"
                    class="mt-6 cursor-pointer rounded-xl border border-stone-200 bg-primary px-4 py-2.5 text-sm font-semibold text-white transition"
                    @click="activeModal = 'post'"
                >
                    <AppIcon
                        name="fa-solid fa-plus"
                        class="mr-2"
                        aria-hidden="true"
                    />
                    Add a memory
                </button>
            </div>

            <InfiniteScroll
                v-else
                data="images"
                class="-mx-4 grid grid-cols-2 items-stretch gap-px sm:-mx-8 lg:mx-0 lg:gap-3 xl:grid-cols-3"
            >
                <article
                    v-for="image in images.data"
                    :key="image.id"
                    class="group min-w-0 lg:flex lg:h-full lg:flex-col lg:rounded-xl lg:border lg:border-stone-200 lg:bg-white lg:p-2 lg:transition lg:duration-200 lg:hover:-translate-y-1"
                >
                    <button
                        type="button"
                        :aria-label="`Open photo: ${image.postTitle || image.name}`"
                        class="block w-full cursor-zoom-in focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-primary"
                        @click="openImage(image)"
                    >
                        <div
                            class="relative aspect-square overflow-hidden bg-canvas lg:rounded-xl lg:border lg:border-stone-200"
                        >
                            <img
                                :src="image.url"
                                :alt="image.name"
                                class="size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                loading="lazy"
                            />
                            <div
                                class="absolute top-3 right-3 hidden size-10 items-center justify-center rounded-xl border border-stone-200 bg-white text-zinc-950 opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100 lg:flex"
                                aria-hidden="true"
                            >
                                <AppIcon
                                    name="fa-solid fa-up-right-from-square"
                                />
                            </div>
                        </div>
                    </button>

                    <div class="hidden min-w-0 flex-1 gap-3 p-3 pt-4 lg:grid">
                        <div class="min-w-0">
                            <h2
                                class="truncate text-base font-semibold text-zinc-950"
                            >
                                {{ image.postTitle ?? 'Timeline image' }}
                            </h2>
                            <p class="mt-1 text-xs font-bold text-zinc-600">
                                {{ image.postDate ?? 'Undated memory' }}
                            </p>
                        </div>

                        <div
                            class="flex flex-col items-start justify-between gap-2 border-t border-stone-200 pt-3 text-xs font-bold text-zinc-600 sm:flex-row sm:items-center"
                        >
                            <span>{{ formatFileSize(image.size) }}</span>
                            <a
                                :href="image.downloadUrl"
                                class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-mint px-2.5 py-1.5 font-semibold text-zinc-950 transition"
                            >
                                <AppIcon
                                    name="fa-solid fa-download"
                                    aria-hidden="true"
                                />
                                Download
                            </a>
                        </div>
                    </div>
                </article>
                <template #loading>
                    <p
                        class="col-span-full p-4 text-center text-sm text-stone-500"
                        role="status"
                    >
                        Loading photos…
                    </p>
                </template>
            </InfiniteScroll>
        </section>

        <ImageViewer
            :image="selectedImage"
            :images="selectedPostImages"
            :title="selectedImage?.postTitle"
            :subtitle="selectedImage?.postDate"
            :notice="mediaError"
            @select="selectImage"
            @close="selectedImage = null"
        />

        <Modal
            :show="activeModal === 'post'"
            mobile-fullscreen
            title="Add a memory"
            eyebrow="New memory"
            @close="activeModal = null"
        >
            <PostForm @close="activeModal = null" />
        </Modal>

        <Modal
            :show="activeModal === 'search'"
            title="Search the timeline"
            eyebrow="Find memories"
            @close="activeModal = null"
        >
            <Search @close="activeModal = null" />
        </Modal>
    </BaseLayout>
</template>

<script setup lang="ts">
import { Head, InfiniteScroll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ImageViewer from '@/components/ImageViewer.vue';
import Modal from '@/components/Modal.vue';
import PageHeader from '@/components/PageHeader.vue';
import PostForm from '@/components/PostForm.vue';
import Search from '@/components/Search.vue';
import Sidebar from '@/components/Sidebar.vue';
import { usePostMedia } from '@/composables/usePostMedia';
import BaseLayout from '@/layouts/BaseLayout.vue';
import type { GalleryImage, ImageViewerMedia, PaginatedGallery } from '@/types';

type ActiveModal = 'post' | 'search' | null;

const props = defineProps<{
    images: PaginatedGallery<GalleryImage>;
}>();

const activeModal = ref<ActiveModal>(null);
const selectedImage = ref<GalleryImage | null>(null);

const {
    load: loadPostMedia,
    media: postMedia,
    error: mediaError,
} = usePostMedia<GalleryImage>('image');
const openImage = (image: GalleryImage) => {
    selectedImage.value = image;
    void loadPostMedia(image);
};
const selectedPostImages = computed(() =>
    (postMedia.value && postMedia.value.postId === selectedImage.value?.postId
        ? postMedia.value.items
        : props.images.data
    ).filter((image) => image.postId === selectedImage.value?.postId),
);
const selectImage = (image: ImageViewerMedia) => {
    selectedImage.value =
        selectedPostImages.value.find(
            (candidate) => candidate.id === image.id,
        ) ?? null;
};

const formatFileSize = (bytes: number) => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>
