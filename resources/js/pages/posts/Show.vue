<template>
    <BaseLayout>
        <template #sidebar
            ><Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
        /></template>
        <Head :title="post.title" />
        <section class="grid gap-5">
            <Link
                :href="home()"
                class="flex min-h-11 w-fit items-center gap-2 text-sm font-semibold text-primary"
                ><ArrowLeft :size="18" />Back to the timeline</Link
            >
            <TimelineCard
                :post="post"
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
import { Head, Link, router } from '@inertiajs/vue3';
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
const activeModal = ref<'post' | 'search' | null>(null);
</script>
