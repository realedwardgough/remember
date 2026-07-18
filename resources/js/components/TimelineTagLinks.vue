<template>
    <div
        v-if="tags.length"
        class="flex flex-wrap gap-1.5"
        :class="spacingClass"
    >
        <Link
            v-for="tag in tags"
            :key="tag"
            :href="home({ query: queryForTag(tag) })"
            class="border-2 border-zinc-950 px-2 py-1 text-xs font-black shadow-[2px_2px_0_0_#18181b] transition-[background-color,translate] hover:-translate-x-0.5 hover:-translate-y-0.5"
            :class="
                activeTag === cleanTag(tag)
                    ? 'bg-[#e06573] text-white'
                    : 'bg-white text-zinc-700 hover:bg-[#fff0b8]'
            "
        >
            {{ tag }}
        </Link>
    </div>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { home } from '@/routes';
import type { PageProps } from '@/types';

withDefaults(
    defineProps<{
        tags: string[];
        spacingClass?: string;
    }>(),
    {
        spacingClass: 'mt-3',
    },
);

const page = usePage<PageProps>();

const activeTag = computed(() => page.props.filters.tag);

const cleanTag = (tag: string) => tag.replace(/^#/, '').toLowerCase();

const queryForTag = (tag: string) => ({
    search: page.props.filters.search || undefined,
    tag: activeTag.value === cleanTag(tag) ? undefined : cleanTag(tag),
    type: page.props.filters.type || undefined,
    author: page.props.filters.author || undefined,
});
</script>
