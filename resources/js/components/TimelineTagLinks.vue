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
            class="inline-flex min-h-11 items-center rounded-xl border border-stone-200 px-3 py-2 text-xs font-semibold transition-[background-color,translate]"
            :class="
                activeTag === cleanTag(tag)
                    ? 'bg-primary text-white'
                    : 'bg-lilac text-primary hover:bg-butter'
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
