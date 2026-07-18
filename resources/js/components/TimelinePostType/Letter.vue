<template>
    <li
        class="relative grid grid-cols-1 gap-3 sm:grid-cols-[6rem_minmax(0,1fr)] sm:gap-2.5"
    >
        <div
            class="z-10 flex h-fit flex-row flex-wrap items-center gap-3 bg-[#fff9e8] pb-1 sm:flex-col sm:gap-3 sm:pt-2.5 sm:pb-0"
        >
            <div
                class="flex size-11 -rotate-2 items-center justify-center border-3 border-zinc-950 bg-[#eee6ff] text-zinc-950 shadow-[4px_4px_0_0_#a78bfa] sm:mb-2 sm:size-12"
                aria-label="Letter"
            >
                <i class="fa-solid fa-envelope-open-text text-lg"></i>
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
                <TimelinePostActions
                    :post="post"
                    @edit="emit('editPost', $event)"
                />
            </div>
        </div>

        <div class="h-fit min-w-0">
            <article
                class="border-3 border-zinc-950 bg-[#eee6ff] p-4 shadow-[6px_6px_0_0_#a78bfa] sm:p-5"
            >
                <div class="flex min-w-0 flex-col gap-4">
                    <p
                        class="w-fit border-2 border-zinc-950 bg-white px-2 py-1 text-xs font-black tracking-[0.2em] text-violet-800 uppercase"
                    >
                        Private letter
                    </p>
                    <h2
                        class="text-xl font-black tracking-[0.12em] text-zinc-950 uppercase"
                    >
                        {{ post.title }}
                    </h2>
                    <p
                        class="text-sm leading-7 whitespace-pre-line text-slate-700"
                    >
                        {{ post.content }}
                    </p>
                </div>

                <TimelineMedia :post="post" />

                <TimelineTagLinks :tags="post.tags" />
            </article>
        </div>
    </li>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
</script>
