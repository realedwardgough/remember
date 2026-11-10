<template>
    <BaseLayout>
        <template #sidebar>
            <Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
            />
        </template>

        <Head title="Videos" />

        <section class="grid gap-3 lg:gap-6">
            <header class="flex items-center justify-between gap-3 lg:hidden">
                <h1 class="text-lg font-semibold">Videos</h1>
                <span class="text-xs text-stone-500"
                    >{{ videos.length }}
                    {{ videos.length === 1 ? 'video' : 'videos' }}</span
                >
            </header>
            <PageHeader
                class="hidden lg:flex"
                title="Memories in motion"
                eyebrow="Family videos"
                description="All your family videos, together in one place."
            >
                <span
                    class="rounded-full bg-mint px-4 py-2 text-sm text-stone-600"
                    >{{ videos.length }}
                    {{ videos.length === 1 ? 'video' : 'videos' }}</span
                >
            </PageHeader>

            <div
                v-if="videos.length === 0"
                class="rounded-xl border border-stone-200 bg-white p-7 text-center sm:p-10"
            >
                <div
                    class="mx-auto flex size-16 items-center justify-center rounded-xl border border-stone-200 bg-primary text-2xl text-white"
                    aria-hidden="true"
                >
                    <AppIcon name="fa-solid fa-video" />
                </div>
                <h2 class="mt-6 text-2xl font-semibold text-zinc-950">
                    Your video collection is ready
                </h2>
                <p
                    class="mx-auto mt-2 max-w-md text-sm leading-6 font-medium text-zinc-700"
                >
                    Create a memory with videos and they will appear here, ready
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
                class="grid items-stretch gap-3 sm:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="video in videos"
                    :key="video.id"
                    class="flex min-w-0 flex-col rounded-xl border border-stone-200 bg-white p-2"
                >
                    <TimelineVideo :media="video" @updated="updateVideo" />

                    <div class="grid min-w-0 flex-1 gap-3 p-3 pt-4">
                        <div class="min-w-0">
                            <h2
                                class="truncate text-base font-semibold text-zinc-950"
                            >
                                {{ video.postTitle ?? 'Timeline video' }}
                            </h2>
                            <p class="mt-1 text-xs font-bold text-zinc-600">
                                {{ video.postDate ?? 'Undated memory' }}
                            </p>
                        </div>

                        <div
                            class="flex flex-col items-start justify-between gap-2 border-t border-stone-200 pt-3 text-xs font-bold text-zinc-600 sm:flex-row sm:items-center"
                        >
                            <span>{{ formatFileSize(video.size) }}</span>
                            <a
                                :href="video.downloadUrl"
                                class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-mint px-2.5 py-1.5 font-semibold text-zinc-950 transition"
                            >
                                <AppIcon
                                    name="fa-solid fa-download"
                                    aria-hidden="true"
                                />
                                {{
                                    video.status === 'ready' || !video.status
                                        ? 'Download'
                                        : 'Download original'
                                }}
                            </a>
                        </div>
                    </div>
                </article>
            </div>
        </section>

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
import { ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import Modal from '@/components/Modal.vue';
import PageHeader from '@/components/PageHeader.vue';
import PostForm from '@/components/PostForm.vue';
import Search from '@/components/Search.vue';
import Sidebar from '@/components/Sidebar.vue';
import TimelineVideo from '@/components/TimelineVideo.vue';
import BaseLayout from '@/layouts/BaseLayout.vue';
import type { GalleryVideo, TimelineMedia } from '@/types';

type ActiveModal = 'post' | 'search' | null;

const props = defineProps<{
    videos: GalleryVideo[];
}>();

const activeModal = ref<ActiveModal>(null);
const videos = ref(props.videos);
watch(
    () => props.videos,
    (value) => {
        videos.value = value;
    },
);
const updateVideo = (media: TimelineMedia) => {
    const video = videos.value.find((candidate) => candidate.id === media.id);

    if (video) {
        Object.assign(video, media);
    }
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
