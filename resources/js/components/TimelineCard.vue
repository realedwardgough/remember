<template>
    <article class="surface min-w-0 overflow-hidden">
        <header class="flex items-center gap-3 px-4 pt-4 sm:px-6 sm:pt-5">
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-2xl text-primary"
                :class="typeStyles[post.type]"
                ><component
                    :is="typeIcons[post.type]"
                    :size="19"
                    aria-hidden="true"
            /></span>
            <div class="min-w-0 flex-1">
                <Link
                    :href="home({ query: queryForAuthor(post.author) })"
                    class="block truncate text-sm font-semibold text-stone-700 hover:text-primary"
                    >@{{ post.author }}</Link
                >
                <time
                    :datetime="post.datetime"
                    class="text-xs text-stone-500"
                    >{{ post.date }}</time
                >
            </div>
            <span class="hidden text-xs text-stone-400 sm:inline">{{
                post.type === 'Letter' ? 'Private letter' : post.type
            }}</span>
            <TimelinePostActions
                :post="post"
                @edit="emit('editPost', $event)"
            />
        </header>
        <div class="px-4 pb-4 sm:px-6 sm:pb-5">
            <TimelineMedia :post="post" />
            <div class="mt-4">
                <p
                    v-if="post.type === 'Letter'"
                    class="mb-2 flex items-center gap-1.5 text-xs font-medium text-primary"
                >
                    <LockKeyhole :size="13" />Only you can read this letter
                </p>
                <h2 class="story-font text-2xl leading-snug break-words">
                    {{ post.title }}
                </h2>
                <p
                    v-if="post.content"
                    class="mt-2 text-sm leading-7 whitespace-pre-line text-stone-600"
                >
                    {{ post.content }}
                </p>
            </div>
            <TimelineTagLinks :tags="post.tags" />
            <TimelineEngagement
                v-if="post.type !== 'Letter'"
                :post="post"
                :allow-comments="post.type !== 'Event'"
            />
        </div>
    </article>
</template>
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, Flag, Image, LockKeyhole, Mail } from 'lucide-vue-next';
import TimelineEngagement from '@/components/TimelineEngagement.vue';
import TimelineMedia from '@/components/TimelineMedia.vue';
import TimelinePostActions from '@/components/TimelinePostActions.vue';
import TimelineTagLinks from '@/components/TimelineTagLinks.vue';
import { home } from '@/routes';
import type { PageProps, TimelinePost } from '@/types';
defineProps<{ post: TimelinePost }>();
const emit = defineEmits<{ editPost: [post: TimelinePost] }>();
const page = usePage<PageProps>();
const typeStyles = {
    Memory: 'bg-blush',
    Milestone: 'bg-butter',
    Event: 'bg-mint',
    Letter: 'bg-lilac',
};
const typeIcons = {
    Memory: Image,
    Milestone: Flag,
    Event: CalendarDays,
    Letter: Mail,
};
const queryForAuthor = (author: string) => ({
    ...page.props.filters,
    author:
        page.props.filters.author === author.toLowerCase()
            ? undefined
            : author.toLowerCase(),
});
</script>
