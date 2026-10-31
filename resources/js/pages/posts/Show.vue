<template>
    <BaseLayout>
        <template #sidebar
            ><Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
        /></template>
        <Head :title="post.title" />
        <section class="-mx-4 grid gap-0 sm:-mx-8 lg:mx-0 lg:gap-5">
            <button
                type="button"
                @click="backToTimeline"
                class="flex min-h-11 w-fit items-center gap-2 px-4 text-sm font-semibold text-primary sm:px-8 lg:px-0"
            >
                <ArrowLeft :size="18" />Back to the timeline
            </button>
            <TimelineCard
                :post="post"
                detail
                @edit-post="router.visit(editPost.url($event.id))"
            />
        </section>
        <Modal
            :show="activeModal === 'post'"
            mobile-fullscreen
            title="Add a memory"
            eyebrow="New memory"
            @close="activeModal = null"
            ><PostForm @close="activeModal = null"
        /></Modal>
        <Modal
            :show="activeModal === 'search'"
            title="Search the timeline"
            eyebrow="Find memories"
            @close="activeModal = null"
            ><Search @close="activeModal = null"
        /></Modal>
    </BaseLayout>
</template>
<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import Modal from '@/components/Modal.vue';
import PostForm from '@/components/PostForm.vue';
import Search from '@/components/Search.vue';
import Sidebar from '@/components/Sidebar.vue';
import TimelineCard from '@/components/TimelineCard.vue';
import BaseLayout from '@/layouts/BaseLayout.vue';
import { home } from '@/routes';
import { edit as editPost } from '@/routes/posts';
import type { TimelinePost } from '@/types';
defineProps<{ post: TimelinePost }>();
const page = usePage();
const backToTimeline = () => {
    const returnTo = new URLSearchParams(page.url.split('?')[1]).get(
        'returnTo',
    );

    if (
        returnTo &&
        /^\/(?:\?.*)?$/.test(returnTo) &&
        window.history.length > 1
    ) {
        window.history.back();
    } else {
        router.visit(home());
    }
};
const activeModal = ref<'post' | 'search' | null>(null);
</script>
