<template>
    <article
        ref="card"
        class="surface relative min-w-0 overflow-hidden"
        :class="{
            'max-lg:rounded-none max-lg:border-x-0 max-lg:border-t-0 max-lg:shadow-none':
                mobileFeed || detail,
            'max-lg:touch-manipulation': post.type !== 'Letter',
        }"
        @click="openPost"
        @touchstart.passive="startPostTouch"
        @touchmove.passive="handleTouchMove"
        @touchend="endPostTouch"
        @touchcancel="cancelTap"
        @pointerdown.capture="cancelInteractiveTap"
    >
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="scale-50 opacity-0"
            enter-to-class="scale-100 opacity-100"
            leave-active-class="transition duration-300 ease-in"
            leave-to-class="scale-125 opacity-0"
        >
            <span
                v-if="heartFeedback"
                class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-1/2 text-rose-500 drop-shadow-lg"
                :style="{ left: `${heartPoint.x}px`, top: `${heartPoint.y}px` }"
                aria-hidden="true"
                ><Heart
                    :size="76"
                    fill="currentColor"
                    stroke="white"
                    :stroke-width="1.5"
            /></span>
        </Transition>
        <span class="sr-only" role="status">{{
            heartFeedback ? 'Post liked' : ''
        }}</span>
        <Link
            v-if="!detail"
            :href="postUrl"
            class="sr-only focus:not-sr-only focus:block focus:p-4 lg:hidden"
            >Open {{ post.title }} and comments</Link
        >
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
            <TimelineMedia :post="post" @double-tap="likePost" />
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
                :heart-processing="heartProcessing"
                @toggle-heart="togglePostHeart()"
                :allow-comments="post.type !== 'Event'"
                :comments-open="detail && isMobile ? true : undefined"
                :open-post="!detail && isMobile ? postUrl : undefined"
            />
        </div>
    </article>
</template>
<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import {
    CalendarDays,
    Flag,
    Heart,
    Image,
    LockKeyhole,
    Mail,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { toggle as toggleHeart } from '@/actions/App/Http/Controllers/HeartController';
import TimelineEngagement from '@/components/TimelineEngagement.vue';
import TimelineMedia from '@/components/TimelineMedia.vue';
import TimelinePostActions from '@/components/TimelinePostActions.vue';
import TimelineTagLinks from '@/components/TimelineTagLinks.vue';
import { useDoubleTap } from '@/composables/useDoubleTap';
import type { TapPosition } from '@/composables/useDoubleTap';
import { home } from '@/routes';
import { show } from '@/routes/posts';
import type { PageProps, TimelinePost } from '@/types';
const props = defineProps<{
    post: TimelinePost;
    detail?: boolean;
    mobileFeed?: boolean;
}>();
const isMobile = useMediaQuery('(max-width: 1023px)');
const emit = defineEmits<{ editPost: [post: TimelinePost] }>();
const page = usePage<PageProps>();
const postUrl = computed(() =>
    show.url(props.post.id, { query: { returnTo: page.url } }),
);
const card = ref<HTMLElement | null>(null);
const heartProcessing = ref(false);
const heartFeedback = ref(false);
const heartPoint = ref({ x: 0, y: 0 });
let feedbackTimer: ReturnType<typeof setTimeout> | undefined;
const showHeart = (event: TapPosition) => {
    const bounds = card.value?.getBoundingClientRect();

    if (!bounds) {
        return;
    }

    heartPoint.value = {
        x: event.clientX - bounds.left,
        y: event.clientY - bounds.top,
    };
    heartFeedback.value = true;
    clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => {
        heartFeedback.value = false;
    }, 700);
};
const togglePostHeart = (onSuccess?: () => void) => {
    if (heartProcessing.value || props.post.type === 'Letter') {
        return;
    }

    heartProcessing.value = true;
    router.visit(toggleHeart(props.post.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess,
        onFinish: () => {
            heartProcessing.value = false;
        },
    });
};
const likePost = (event: TapPosition) => {
    if (
        !isMobile.value ||
        props.post.type === 'Letter' ||
        heartProcessing.value
    ) {
        return;
    }

    if (props.post.heartedByViewer) {
        showHeart(event);

        return;
    }

    togglePostHeart(() => showHeart(event));
};
const {
    handleTap,
    handleTouchStart,
    handleTouchMove,
    handleTouchEnd,
    cancelTap,
} = useDoubleTap({
    enabled: () => isMobile.value && props.post.type !== 'Letter',
    onDoubleTap: likePost,
});
const interactiveElements =
    'a, button, input, textarea, select, video, [contenteditable], [role="dialog"], [data-post-engagement]';
const cancelInteractiveTap = (event: PointerEvent) => {
    if ((event.target as HTMLElement).closest(interactiveElements)) {
        cancelTap();
    }
};
const startPostTouch = (event: TouchEvent) => {
    if ((event.target as HTMLElement).closest(interactiveElements)) {
        cancelTap();

        return;
    }

    handleTouchStart(event);
};
const navigatePost = () => {
    if (!props.detail) {
        router.visit(postUrl.value);
    }
};
const endPostTouch = (event: TouchEvent) => {
    if ((event.target as HTMLElement).closest(interactiveElements)) {
        return;
    }

    if (window.getSelection()?.toString()) {
        cancelTap();

        return;
    }

    handleTouchEnd(event, navigatePost);
};
onBeforeUnmount(() => clearTimeout(feedbackTimer));
const openPost = (event: MouseEvent) => {
    if (
        !isMobile.value ||
        event.defaultPrevented ||
        window.getSelection()?.toString()
    ) {
        return;
    }

    if ((event.target as HTMLElement).closest(interactiveElements)) {
        return;
    }

    handleTap(event, navigatePost);
};
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
