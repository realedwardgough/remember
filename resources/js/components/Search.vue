<template>
    <section
        class="h-fit self-start inert:pointer-events-none inert:opacity-60"
    >
        <div
            class="mb-5 border-3 border-zinc-950 bg-[#b9eadf] p-4 shadow-[4px_4px_0_0_#77c8b5]"
        >
            <h2 class="text-lg font-black text-zinc-950 uppercase">
                Find a moment
            </h2>
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
                class="min-h-12 border-3 border-zinc-950 bg-white px-4 text-base font-semibold text-zinc-950 placeholder:font-medium placeholder:text-zinc-400 focus:shadow-[4px_4px_0_0_#e06573] focus:outline-none"
            />
            <div class="flex gap-2">
                <button
                    type="submit"
                    class="inline-flex min-h-11 flex-1 items-center justify-center border-3 border-zinc-950 bg-[#e06573] px-4 text-sm font-black text-white shadow-[4px_4px_0_0_#18181b] hover:-translate-x-0.5 hover:-translate-y-0.5"
                >
                    Search
                </button>
                <Link
                    v-if="filters.search !== ''"
                    :href="home({ query: queryForSearch('') })"
                    class="inline-flex min-h-11 items-center justify-center border-3 border-zinc-950 bg-white px-4 text-sm font-black text-zinc-700 hover:bg-[#fff0b8]"
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
