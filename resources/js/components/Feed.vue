<template>
    <section aria-labelledby="timeline-heading" class="mb-24 grid gap-2">
        <div
            v-if="timelinePosts.data.length === 0"
            class="border-3 border-dashed border-zinc-950 bg-white p-6 text-sm leading-6 font-semibold text-zinc-700 shadow-[5px_5px_0_0_#f8cc5d]"
        >
            {{ emptyMessage }}
        </div>

        <InfiniteScroll
            v-else
            data="timelinePosts"
            as="ol"
            class="relative grid gap-14 before:absolute before:top-2 before:bottom-0 before:left-12 before:hidden before:w-1 before:bg-zinc-950 sm:gap-12 sm:before:block"
        >
            <template v-for="post in timelinePosts.data" :key="post.id">
                <Event
                    v-if="post.type === 'Event'"
                    :post="post"
                    @edit-post="emitEditPost($event)"
                />
                <Memory
                    v-if="post.type === 'Memory'"
                    :post="post"
                    @edit-post="emitEditPost($event)"
                />
                <Letter
                    v-if="post.type === 'Letter'"
                    :post="post"
                    @edit-post="emitEditPost($event)"
                />
                <Milestone
                    v-if="post.type === 'Milestone'"
                    :post="post"
                    @edit-post="emitEditPost($event)"
                />
            </template>
        </InfiniteScroll>
    </section>
</template>

<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { computed } from 'vue';
import Event from '@/components/TimelinePostType/Event.vue';
import Letter from '@/components/TimelinePostType/Letter.vue';
import Memory from '@/components/TimelinePostType/Memory.vue';
import Milestone from '@/components/TimelinePostType/Milestone.vue';
import type { PaginatedTimelinePosts, TimelinePost } from '@/types';

const emit = defineEmits<{
    editPost: [post: TimelinePost];
}>();

const emitEditPost = (post: TimelinePost) => {
    emit('editPost', post);
};

const props = withDefaults(
    defineProps<{
        timelinePosts: PaginatedTimelinePosts;
        hasActiveFilters?: boolean;
    }>(),
    {
        hasActiveFilters: false,
    },
);

const emptyMessage = computed(() => {
    return props.hasActiveFilters
        ? 'No posts match those filters.'
        : 'No posts yet. Create the first memory, milestone, event, or letter above.';
});
</script>
