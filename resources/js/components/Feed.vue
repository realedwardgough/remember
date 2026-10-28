<template>
    <section aria-label="Family timeline">
        <div
            v-if="!timelinePosts.data.length"
            class="surface px-6 py-12 text-center"
        >
            <Heart :size="32" class="mx-auto mb-4 text-primary" />
            <h2 class="story-font text-2xl">
                {{
                    hasActiveFilters
                        ? 'No moments found'
                        : 'Your story starts here'
                }}
            </h2>
            <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-stone-500">
                {{
                    hasActiveFilters
                        ? 'Try another search or clear your filters to see more memories.'
                        : 'Tap Add to save a photo, a little story or a moment you never want to forget.'
                }}
            </p>
        </div>
        <InfiniteScroll v-else data="timelinePosts" as="ol" class="grid gap-5">
            <li
                v-for="(post, index) in timelinePosts.data"
                :key="post.id"
                class="min-w-0"
            >
                <h2
                    v-if="
                        index === 0 ||
                        monthKey(post) !==
                            monthKey(timelinePosts.data[index - 1])
                    "
                    class="mb-4 flex items-center gap-3 text-sm font-semibold text-stone-600"
                    :class="index > 0 ? 'mt-5' : ''"
                >
                    <span>{{ monthLabel(post) }}</span
                    ><span
                        class="h-px flex-1 bg-stone-200"
                        aria-hidden="true"
                    ></span>
                </h2>
                <TimelineCard
                    :post="post"
                    @edit-post="emit('editPost', $event)"
                />
            </li>
        </InfiniteScroll>
    </section>
</template>
<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { Heart } from 'lucide-vue-next';
import TimelineCard from '@/components/TimelineCard.vue';
import type { PaginatedTimelinePosts, TimelinePost } from '@/types';
withDefaults(
    defineProps<{
        timelinePosts: PaginatedTimelinePosts;
        hasActiveFilters?: boolean;
    }>(),
    { hasActiveFilters: false },
);
const emit = defineEmits<{ editPost: [post: TimelinePost] }>();
const monthKey = (post: TimelinePost) => post.datetime.slice(0, 7);
const monthLabel = (post: TimelinePost) =>
    new Intl.DateTimeFormat('en-GB', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${monthKey(post)}-01T00:00:00Z`));
</script>
