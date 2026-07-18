<template>
    <BaseLayout>
        <template #sidebar>
            <Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
            />
        </template>

        <Head title="Gallery" />

        <section class="grid gap-8 pb-24 md:pb-0">
            <header
                class="relative overflow-hidden border-4 border-zinc-950 bg-[#77c8b5] p-5 shadow-[8px_8px_0_#18181b] sm:p-8"
            >
                <div
                    class="absolute -top-12 -right-10 size-36 rotate-12 border-4 border-zinc-950 bg-[#f4ce63] sm:size-44"
                    aria-hidden="true"
                ></div>
                <div
                    class="absolute right-28 -bottom-14 size-28 -rotate-6 border-4 border-zinc-950 bg-[#e06573] sm:right-40 sm:size-36"
                    aria-hidden="true"
                ></div>

                <p
                    class="relative z-10 inline-flex border-3 border-zinc-950 bg-white px-3 py-1 text-xs font-black tracking-[0.2em] text-zinc-950 uppercase shadow-[3px_3px_0_#18181b]"
                >
                    Family gallery
                </p>
                <div
                    class="relative z-10 mt-6 flex flex-col gap-5 md:flex-row md:items-end md:justify-between"
                >
                    <div class="max-w-2xl">
                        <h1
                            class="text-4xl leading-[0.95] font-black tracking-tight text-zinc-950 sm:text-6xl"
                        >
                            All the little moments
                        </h1>
                        <p
                            class="mt-4 max-w-xl text-sm leading-6 font-semibold text-zinc-800 sm:text-base"
                        >
                            A visual wall of every image preserved in your
                            shared timeline, gathered together in one place.
                        </p>
                    </div>

                    <div
                        class="w-fit shrink-0 border-3 border-zinc-950 bg-white px-4 py-3 font-black text-zinc-950 shadow-[4px_4px_0_#18181b]"
                        aria-label="Gallery image count"
                    >
                        <span class="text-2xl leading-none">{{
                            images.length
                        }}</span>
                        <span class="ml-1 text-xs tracking-wider uppercase">
                            {{ images.length === 1 ? 'image' : 'images' }}
                        </span>
                    </div>
                </div>
            </header>

            <div
                v-if="images.length === 0"
                class="border-4 border-zinc-950 bg-white p-7 text-center shadow-[7px_7px_0_#f4ce63] sm:p-10"
            >
                <div
                    class="mx-auto flex size-16 -rotate-3 items-center justify-center border-3 border-zinc-950 bg-[#e06573] text-2xl text-white shadow-[4px_4px_0_#18181b]"
                    aria-hidden="true"
                >
                    <i class="fa-solid fa-images"></i>
                </div>
                <h2 class="mt-6 text-2xl font-black text-zinc-950">
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
                    class="mt-6 cursor-pointer border-3 border-zinc-950 bg-[#e06573] px-4 py-2.5 text-sm font-black text-white shadow-[4px_4px_0_#18181b] transition hover:-translate-y-0.5 hover:shadow-[6px_6px_0_#18181b]"
                    @click="activeModal = 'post'"
                >
                    <i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>
                    Add a memory
                </button>
            </div>

            <div
                v-else
                class="grid grid-cols-1 items-stretch gap-5 sm:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="image in images"
                    :key="image.id"
                    class="group flex h-full flex-col border-4 border-zinc-950 bg-white p-2 shadow-[6px_6px_0_#18181b] transition duration-200 hover:-translate-y-1 hover:shadow-[9px_9px_0_#e06573]"
                >
                    <button
                        type="button"
                        class="block w-full cursor-zoom-in focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-[#e06573]"
                        @click="selectedImage = image"
                    >
                        <div
                            class="relative aspect-square overflow-hidden border-3 border-zinc-950 bg-[#f7f1e7]"
                        >
                            <img
                                :src="image.url"
                                :alt="image.name"
                                class="size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                loading="lazy"
                            />
                            <div
                                class="absolute top-3 right-3 flex size-10 items-center justify-center border-3 border-zinc-950 bg-white text-zinc-950 opacity-0 shadow-[3px_3px_0_#18181b] transition group-focus-within:opacity-100 group-hover:opacity-100"
                                aria-hidden="true"
                            >
                                <i class="fa-solid fa-up-right-from-square"></i>
                            </div>
                        </div>
                    </button>

                    <div class="grid flex-1 gap-3 p-3 pt-4">
                        <div class="min-w-0">
                            <h2
                                class="truncate text-base font-black text-zinc-950"
                            >
                                {{ image.postTitle ?? 'Timeline image' }}
                            </h2>
                            <p class="mt-1 text-xs font-bold text-zinc-600">
                                {{ image.postDate ?? 'Undated memory' }}
                            </p>
                        </div>

                        <div
                            class="flex items-center justify-between gap-3 border-t-2 border-zinc-950 pt-3 text-xs font-bold text-zinc-600"
                        >
                            <span>{{ formatFileSize(image.size) }}</span>
                            <a
                                :href="image.downloadUrl"
                                class="inline-flex items-center gap-2 border-2 border-zinc-950 bg-[#f4ce63] px-2.5 py-1.5 font-black text-zinc-950 shadow-[2px_2px_0_#18181b] transition hover:-translate-y-0.5 hover:shadow-[3px_3px_0_#18181b]"
                            >
                                <i
                                    class="fa-solid fa-download"
                                    aria-hidden="true"
                                ></i>
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
            title="Preserve a new memory"
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
import ImageViewer from '@/components/ImageViewer.vue';
import Modal from '@/components/Modal.vue';
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
