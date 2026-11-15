<template>
    <div
        v-if="tags.length"
        class="flex flex-wrap gap-x-3 gap-y-1 lg:gap-1.5"
        :class="spacingClass"
    >
        <Link
            v-for="tag in tags"
            :key="tag"
            :href="home({ query: queryForTag(tag) })"
            class="inline-flex min-h-6 items-center py-0.5 text-xs font-semibold text-primary transition-[background-color,translate] lg:min-h-11 lg:rounded-xl lg:border lg:border-stone-200 lg:px-3 lg:py-2"
            :class="
                activeTag === cleanTag(tag)
                    ? 'underline underline-offset-2 lg:bg-primary lg:text-white lg:no-underline'
                    : 'hover:underline lg:bg-lilac lg:hover:bg-butter lg:hover:no-underline'
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
        spacingClass: 'mt-1 lg:mt-3',
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
