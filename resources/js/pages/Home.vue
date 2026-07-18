<template>
    <BaseLayout>
        <template #sidebar>
            <Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
            />
        </template>

        <Head :title="timeline.name" />

        <section class="grid content-start gap-8">
            <header
                class="relative overflow-hidden border-4 border-zinc-950 bg-[#e06573] p-6 shadow-[8px_8px_0_0_#18181b] sm:p-9"
            >
                <p
                    class="relative z-10 w-fit -rotate-1 border-3 border-zinc-950 bg-[#f8cc5d] px-3 py-1.5 text-xs font-black tracking-[0.24em] text-zinc-950 uppercase shadow-[4px_4px_0_0_#18181b]"
                >
                    {{ timeline.name }}
                </p>
                <div
                    class="relative z-10 mt-7 flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
                >
                    <div class="max-w-2xl">
                        <h1
                            class="story-font text-5xl leading-none font-bold text-white sm:text-7xl"
                        >
                            All the little moments
                        </h1>
                        <p
                            class="mt-4 max-w-xl text-sm leading-6 font-semibold text-zinc-950 sm:text-base"
                        >
                            {{
                                timeline.description ||
                                'A visual wall of every memory, milestone and event preserved in your shared timeline.'
                            }}
                        </p>
                    </div>
                </div>
                <div
                    aria-hidden="true"
                    class="absolute -right-12 -bottom-16 size-44 rotate-12 border-4 border-zinc-950 bg-[#77c8b5] sm:size-56"
                ></div>
            </header>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div
                    class="hidden border-3 border-zinc-950 bg-white px-4 py-3 text-xs font-black tracking-[0.14em] uppercase shadow-[4px_4px_0_0_#e06573] sm:flex"
                >
                    {{ family_count }}
                    {{
                        family_count === 1 ? 'Family Member' : 'Family Members'
                    }}
                </div>

                <div
                    class="border-3 border-zinc-950 bg-white px-4 py-3 text-xs font-black tracking-[0.14em] uppercase shadow-[4px_4px_0_0_#f8cc5d]"
                >
                    {{ memories_count }}
                    {{
                        memories_count === 1
                            ? 'Memory Shared'
                            : 'Memories Shared'
                    }}
                </div>

                <div
                    class="hidden border-3 border-zinc-950 bg-white px-4 py-3 text-xs font-black tracking-[0.14em] uppercase shadow-[4px_4px_0_0_#77c8b5] sm:flex"
                >
                    {{ media_count }}
                    {{
                        media_count === 1
                            ? 'Moment Captured'
                            : 'Moments Captured'
                    }}
                </div>
            </div>

            <section
                v-if="hasActiveFilters"
                class="border-3 border-zinc-950 bg-white p-5 shadow-[5px_5px_0_0_#18181b]"
                aria-label="Active timeline filters"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-black tracking-[0.2em] text-[#c84f65] uppercase"
                        >
                            Active filters
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            The timeline is currently narrowed down.
                        </p>
                    </div>

                    <Link
                        :href="home()"
                        preserve-scroll
                        class="w-fit border-2 border-zinc-950 bg-[#f8cc5d] px-3 py-1.5 text-xs font-black text-zinc-950 shadow-[3px_3px_0_0_#18181b] hover:bg-[#e06573] hover:text-white"
                    >
                        Clear all
                    </Link>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <template
                        v-for="filter in activeFilterChips"
                        :key="filter.key"
                    >
                        <span
                            class="inline-flex items-center gap-2 border-2 border-zinc-950 bg-[#fff0b8] px-3 py-1.5 text-sm font-bold text-zinc-800"
                        >
                            <span class="text-[#e06573]">{{
                                filter.label
                            }}</span>
                            <span>{{ filter.value }}</span>
                            <Link
                                :href="
                                    home({
                                        query: queryWithoutFilter(filter.key),
                                    })
                                "
                                preserve-scroll
                                class="inline-flex size-5 items-center justify-center border-2 border-zinc-950 bg-white text-zinc-700 hover:bg-[#e06573] hover:text-white"
                                :aria-label="`Clear ${filter.label} filter`"
                            >
                                <i class="fa-solid fa-xmark text-[10px]"></i>
                            </Link>
                        </span>
                    </template>
                </div>
            </section>

            <Feed
                :timelinePosts="timelinePosts"
                :has-active-filters="hasActiveFilters"
                @edit-post="openEditModal"
            />
        </section>

        <Modal
            :show="activeModal === 'post'"
            title="Preserve a new memory"
            eyebrow="New memory"
            @close="closeModal"
        >
            <PostForm @close="closeModal" />
        </Modal>

        <Modal
            :show="activeModal === 'search'"
            title="Search the timeline"
            eyebrow="Find memories"
            @close="closeModal"
        >
            <Search @close="closeModal" />
        </Modal>

        <Modal
            :show="activeModal === 'edit' && selectedEditablePost !== null"
            title="Update this memory"
            eyebrow="Edit post"
            @close="closeModal"
        >
            <EditPostForm
                v-if="selectedEditablePost"
                :post="selectedEditablePost"
                :post-types="postTypes"
                @close="closeModal"
            />
        </Modal>
    </BaseLayout>
</template>

<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import EditPostForm from '@/components/EditPostForm.vue';
import type { EditableTimelinePost } from '@/components/EditPostForm.vue';
import Feed from '@/components/Feed.vue';
import Modal from '@/components/Modal.vue';
import PostForm from '@/components/PostForm.vue';
import Search from '@/components/Search.vue';
import Sidebar from '@/components/Sidebar.vue';
import BaseLayout from '@/layouts/BaseLayout.vue';
import { home } from '@/routes';
import type {
    PageProps,
    PaginatedTimelinePosts,
    TimelineFilters as ActiveTimelineFilters,
    TimelinePost,
    TimelinePostType,
} from '@/types';

const page = usePage<PageProps>();
const timeline = computed(() => page.props.timeline);

const family_count = computed(() => page.props.family_count);
const media_count = computed(() => page.props.media_count);
const memories_count = computed(() => page.props.memories_count);

type ActiveModal = 'post' | 'search' | 'edit' | null;

const activeModal = ref<ActiveModal>(null);
const selectedEditablePost = ref<EditableTimelinePost | null>(null);

const props = defineProps<{
    timelinePosts: PaginatedTimelinePosts;
    filters: ActiveTimelineFilters;
    postTypes: TimelinePostType[];
}>();

const openEditModal = (post: TimelinePost) => {
    selectedEditablePost.value = {
        id: post.id,
        title: post.title,
        content: post.content,
        postType: post.type,
        publishedAt: post.datetime,
    };

    activeModal.value = 'edit';
};

const closeModal = () => {
    activeModal.value = null;
    selectedEditablePost.value = null;
};

const hasActiveFilters = computed(() => {
    return (
        props.filters.search !== '' ||
        props.filters.tag !== '' ||
        props.filters.type !== '' ||
        props.filters.author !== ''
    );
});

type FilterKey = keyof ActiveTimelineFilters;

type ActiveFilterChip = {
    key: FilterKey;
    label: string;
    value: string;
};

const activeFilterChips = computed<ActiveFilterChip[]>(() => {
    const filters: ActiveFilterChip[] = [];

    if (props.filters.search !== '') {
        filters.push({
            key: 'search',
            label: 'Search',
            value: props.filters.search,
        });
    }

    if (props.filters.tag !== '') {
        filters.push({
            key: 'tag',
            label: 'Tag',
            value: `#${props.filters.tag}`,
        });
    }

    if (props.filters.type !== '') {
        filters.push({
            key: 'type',
            label: 'Type',
            value: props.filters.type,
        });
    }

    if (props.filters.author !== '') {
        filters.push({
            key: 'author',
            label: 'Author',
            value: `@${props.filters.author}`,
        });
    }

    return filters;
});

const queryWithoutFilter = (filterKey: FilterKey) => ({
    search:
        filterKey === 'search' ? undefined : props.filters.search || undefined,
    tag: filterKey === 'tag' ? undefined : props.filters.tag || undefined,
    type: filterKey === 'type' ? undefined : props.filters.type || undefined,
    author:
        filterKey === 'author' ? undefined : props.filters.author || undefined,
});
</script>
