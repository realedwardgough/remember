<template>
    <li
        class="relative grid grid-cols-1 gap-3 sm:grid-cols-[6rem_minmax(0,1fr)] sm:gap-2.5"
    >
        <div
            class="z-10 flex h-fit flex-row flex-wrap items-center gap-3 bg-[#fff9e8] pb-1 sm:flex-col sm:gap-3 sm:pt-2.5 sm:pb-0"
        >
            <div
                class="flex size-11 -rotate-2 items-center justify-center border-3 border-zinc-950 bg-white text-zinc-950 shadow-[4px_4px_0_0_#e06573] sm:mb-2 sm:size-12"
                aria-label="Memory"
            >
                <i class="fa-solid fa-camera-retro text-lg"></i>
            </div>
            <div
                class="text-left text-xs leading-tight font-bold text-zinc-600 sm:text-center sm:text-sm sm:font-normal"
            >
                <time :datetime="post.datetime">{{ post.date }}</time>
            </div>
            <Link
                :href="home({ query: queryForAuthor(post.author) })"
                class="text-sm font-black hover:underline sm:mt-2"
                :class="
                    activeAuthor === post.author.toLowerCase()
                        ? 'text-[#e06573]'
                        : 'text-slate-700'
                "
            >
                @{{ post.author }}
            </Link>
            <div
                class="flex basis-full flex-row items-center justify-end gap-2 sm:basis-auto sm:flex-col sm:justify-start"
            >
                <TimelineEngagement
                    v-model:comments-open="showCommentForm"
                    :post="post"
                    rail
                    :show-comments="false"
                />
                <TimelinePostActions
                    :post="post"
                    @edit="emit('editPost', $event)"
                />
            </div>
        </div>

        <div class="min-w-0 p-0 sm:p-1">
            <article
                class="border-3 border-zinc-950 bg-white p-4 shadow-[6px_6px_0_0_#e06573] sm:p-5"
            >
                <TimelineMedia :post="post" />

                <div
                    class="mt-6 flex flex-wrap items-start justify-between gap-3"
                >
                    <div class="flex min-w-0 flex-1 flex-col gap-3">
                        <h2
                            class="mt-2 text-xl font-black tracking-[0.12em] text-[#c84f65] uppercase"
                        >
                            {{ post.title }}
                        </h2>
                        <p class="mt-1.5 text-sm leading-6 text-slate-700">
                            {{ post.content }}
                        </p>
                    </div>
                </div>

                <TimelineTagLinks :tags="post.tags" />
            </article>

            <TimelineEngagement
                v-model:comments-open="showCommentForm"
                :post="post"
                :show-actions="false"
            />
        </div>
    </li>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TimelineEngagement from '@/components/TimelineEngagement.vue';
import TimelineMedia from '@/components/TimelineMedia.vue';
import TimelinePostActions from '@/components/TimelinePostActions.vue';
import TimelineTagLinks from '@/components/TimelineTagLinks.vue';
import { home } from '@/routes';
import type { PageProps, TimelinePost } from '@/types';

defineProps<{
    post: TimelinePost;
}>();

const page = usePage<PageProps>();
const activeAuthor = computed(() => page.props.filters.author);

const queryForAuthor = (author: string) => {
    const username = author.toLowerCase();

    return {
        search: page.props.filters.search || undefined,
        tag: page.props.filters.tag || undefined,
        type: page.props.filters.type || undefined,
        author: activeAuthor.value === username ? undefined : username,
    };
};

const emit = defineEmits<{
    editPost: [post: TimelinePost];
}>();

const showCommentForm = ref(false);
</script>
