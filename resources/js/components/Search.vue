<template>
    <section
        class="h-fit self-start inert:pointer-events-none inert:opacity-60"
    >
        <div class="mb-5 rounded-xl border border-stone-200 bg-mint p-4">
            <h2 class="text-lg font-semibold text-zinc-950">Find a moment</h2>
            <p class="mt-1 text-sm font-semibold text-zinc-700">
                Search titles, memories, letters, and milestones.
            </p>
        </div>
        <form class="grid gap-4" @submit.prevent="submitSearch">
            <label class="sr-only" for="timeline-search">Search posts</label>
            <input
                id="timeline-search"
                v-model="searchTerm"
                name="search"
                type="search"
                placeholder="What are you looking for?"
                class="min-h-12 rounded-xl border border-stone-200 bg-white px-4 text-base font-semibold text-zinc-950 placeholder:font-medium placeholder:text-zinc-400"
            />
            <div class="flex gap-2">
                <button
                    type="submit"
                    class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-stone-200 bg-primary px-4 text-sm font-semibold text-white"
                >
                    Search
                </button>
                <Link
                    v-if="filters.search !== ''"
                    :href="home({ query: queryForSearch('') })"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-butter"
                    @click="emit('close')"
                >
                    Clear
                </Link>
            </div>
        </form>
    </section>
</template>

<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { home } from '@/routes';
import type { PageProps } from '@/types';

const emit = defineEmits<{
    close: [];
}>();

const page = usePage<PageProps>();

const filters = computed(() => page.props.filters);

const searchTerm = ref(filters.value.search);

watch(
    () => filters.value.search,
    (search) => {
        searchTerm.value = search;
    },
);

const queryForSearch = (search: string) => ({
    search: search || undefined,
    tag: filters.value.tag || undefined,
    type: filters.value.type || undefined,
    author: filters.value.author || undefined,
});

const submitSearch = () => {
    router.visit(
        home({
            query: queryForSearch(searchTerm.value.trim()),
        }),
        {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        },
    );
};
</script>
