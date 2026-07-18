<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EditPostForm from '@/components/EditPostForm.vue';
import BaseLayout from '@/layouts/BaseLayout.vue';
import { home } from '@/routes';
import type { TimelinePostType } from '@/types';

defineProps<{
    post: {
        id: number;
        title: string;
        content: string;
        postType: TimelinePostType;
        publishedAt: string;
    };
    postTypes: TimelinePostType[];
}>();
</script>

<template>
    <BaseLayout>
        <Head title="Edit post" />

        <section class="mt-8 max-w-3xl">
            <div
                class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-500">
                            Edit post
                        </p>
                        <h1
                            class="mt-1 text-2xl font-semibold tracking-normal text-slate-950"
                        >
                            Update the timeline entry
                        </h1>
                    </div>

                    <Link
                        :href="home()"
                        class="inline-flex min-h-9 shrink-0 items-center rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-800"
                    >
                        Back
                    </Link>
                </div>

                <EditPostForm
                    class="mt-6"
                    :post="post"
                    :post-types="postTypes"
                    :cancel-href="home()"
                />
            </div>
        </section>
    </BaseLayout>
</template>
