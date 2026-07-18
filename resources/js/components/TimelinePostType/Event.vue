<template>
    <li
        class="relative grid grid-cols-1 gap-3 sm:grid-cols-[6rem_minmax(0,1fr)] sm:gap-2.5"
    >
        <div
            class="z-10 flex flex-row flex-wrap items-center gap-3 bg-[#fff9e8] pb-1 sm:flex-col sm:gap-2 sm:pt-2.5 sm:pb-0"
        >
            <div
                class="flex size-11 rotate-2 items-center justify-center border-3 border-zinc-950 bg-[#b9eadf] text-zinc-950 shadow-[4px_4px_0_0_#77c8b5] sm:mb-2 sm:size-12"
                aria-label="Event"
            >
                <i class="fa-solid fa-calendar-day text-lg"></i>
            </div>
            <div
                class="text-left text-xs leading-tight font-bold text-zinc-600 sm:text-center sm:text-sm sm:font-normal"
            >
                <time :datetime="post.datetime">{{ post.date }}</time>
            </div>
            <div
                class="flex basis-full flex-row items-center justify-end gap-2 sm:basis-auto sm:flex-col sm:justify-start"
            >
                <TimelineEngagement :post="post" :allow-comments="false" rail />
                <TimelinePostActions
                    :post="post"
                    @edit="emit('editPost', $event)"
                />
            </div>
        </div>

        <div class="flex min-w-0 flex-col justify-center p-0 sm:pt-4 md:pl-2">
            <article
                class="flex flex-col gap-2 border-3 border-zinc-950 bg-[#b9eadf] px-4 py-5 shadow-[6px_6px_0_0_#77c8b5] sm:px-5"
            >
                <div class="flex flex-row justify-between">
                    <div
                        class="flex w-full flex-col items-center gap-x-2.5 gap-y-1 text-sm text-slate-500"
                    >
                        <span
                            class="pt-2 pb-3 text-center text-2xl font-black tracking-[0.14em] text-zinc-950 uppercase"
                        >
                            {{ post.title }}
                        </span>
                    </div>
                </div>

                <TimelineMedia :post="post" />

                <TimelineTagLinks :tags="post.tags" spacing-class="" />
            </article>
        </div>
    </li>
</template>

<script setup lang="ts">
import TimelineEngagement from '@/components/TimelineEngagement.vue';
import TimelineMedia from '@/components/TimelineMedia.vue';
import TimelinePostActions from '@/components/TimelinePostActions.vue';
import TimelineTagLinks from '@/components/TimelineTagLinks.vue';
import type { TimelinePost } from '@/types';

defineProps<{
    post: TimelinePost;
}>();

const emit = defineEmits<{
    editPost: [post: TimelinePost];
}>();
</script>
