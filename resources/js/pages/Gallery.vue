<template>
    <BaseLayout>
        <template #sidebar>
            <Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
            />
        </template>

        <Head title="Gallery" />

        <section class="grid gap-6">
            <PageHeader
                title="A little look back"
                eyebrow="Family photos"
                description="All your captured moments, together in one place."
            >
                <span
                    class="rounded-full bg-mint px-4 py-2 text-sm text-stone-600"
                    >{{ images.length }}
                    {{ images.length === 1 ? 'photo' : 'photos' }}</span
                >
            </PageHeader>

            <div
                v-if="images.length === 0"
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

            <div
                v-else
                class="grid grid-cols-2 items-stretch gap-3 xl:grid-cols-3"
            >
                <article
                    v-for="image in images"
                    :key="image.id"
                    class="group flex h-full flex-col rounded-xl border border-stone-200 bg-white p-2 transition duration-200 hover:-translate-y-1"
                >
                    <button
                        type="button"
                        class="block w-full cursor-zoom-in focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-primary"
                        @click="selectedImage = image"
                    >
                        <div
                            class="relative aspect-square overflow-hidden rounded-xl border border-stone-200 bg-canvas"
                        >
                            <img
                                :src="image.url"
                                :alt="image.name"
                                class="size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                loading="lazy"
                            />
                            <div
                                class="absolute top-3 right-3 flex size-10 items-center justify-center rounded-xl border border-stone-200 bg-white text-zinc-950 opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100"
                                aria-hidden="true"
                            >
                                <AppIcon
                                    name="fa-solid fa-up-right-from-square"
                                />
                            </div>
                        </div>
                    </button>

                    <div
                        class="grid min-w-0 flex-1 gap-3 px-1 py-3 sm:p-3 sm:pt-4"
                    >
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
            </div>
        </section>

        <ImageViewer
            :image="selectedImage"
            :title="selectedImage?.postTitle"
            :subtitle="selectedImage?.postDate"
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
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ImageViewer from '@/components/ImageViewer.vue';
import Modal from '@/components/Modal.vue';
import PageHeader from '@/components/PageHeader.vue';
import PostForm from '@/components/PostForm.vue';
import Search from '@/components/Search.vue';
import Sidebar from '@/components/Sidebar.vue';
import BaseLayout from '@/layouts/BaseLayout.vue';
import type { GalleryImage } from '@/types';

type ActiveModal = 'post' | 'search' | null;

defineProps<{
    images: GalleryImage[];
}>();

const activeModal = ref<ActiveModal>(null);
const selectedImage = ref<GalleryImage | null>(null);

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
