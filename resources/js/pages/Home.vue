<template>
    <BaseLayout>
        <template #sidebar>
            <Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
            />
        </template>

        <Head :title="timeline.name" />

        <PullToRefresh
            :refresh="refreshTimeline"
            :disabled="activeModal !== null"
        >
            <section class="grid content-start gap-3 lg:gap-6">
                <header
                    class="hidden items-start justify-between gap-4 lg:flex"
                >
                    <div>
                        <p class="mb-2 text-xs font-medium text-primary">
                            Our family story
                        </p>
                        <h1 class="page-heading">
                            All the little moments
                            <span class="text-rose-400">♡</span>
                        </h1>
                        <p
                            class="mt-3 max-w-xl text-sm leading-6 text-stone-500"
                        >
                            {{
                                timeline.description ||
                                'Memories, milestones and letters from the people who love you.'
                            }}
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="button-primary hidden shrink-0 sm:flex"
                            @click="activeModal = 'post'"
                        >
                            + Add a memory
                        </button>
                    </div>
                </header>
                <nav
                    aria-label="Timeline categories"
                    class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0"
                >
                    <Link
                        :href="home({ query: { ...filters, type: undefined } })"
                        class="type-chip"
                        :class="
                            !filters.type
                                ? 'bg-primary text-white'
                                : 'bg-white text-stone-600'
                        "
                        :aria-current="!filters.type ? 'page' : undefined"
                        >All moments</Link
                    >
                    <Link
                        v-for="type in postTypes"
                        :key="type"
                        :href="home({ query: { ...filters, type } })"
                        class="type-chip"
                        :class="
                            filters.type === type
                                ? 'bg-primary text-white'
                                : 'bg-white text-stone-600'
                        "
                        :aria-current="
                            filters.type === type ? 'page' : undefined
                        "
                        >{{
                            {
                                Memory: 'Memories',
                                Milestone: 'Milestones',
                                Letter: 'Letters',
                                Event: 'Events',
                            }[type]
                        }}</Link
                    >
                    <Link
                        :href="gallery()"
                        class="type-chip bg-white text-stone-600 lg:hidden"
                    >
                        <AppIcon name="fa-solid fa-images" /> Photos
                    </Link>
                </nav>

                <section
                    v-if="hasActiveFilters"
                    class="flex items-center gap-2 rounded-xl border border-stone-200 bg-white p-2 lg:block lg:p-5"
                    aria-label="Active timeline filters"
                >
                    <div
                        class="order-last shrink-0 lg:flex lg:items-center lg:justify-between lg:gap-3"
                    >
                        <div class="hidden lg:block">
                            <p
                                class="text-xs font-semibold tracking-normal text-primary"
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
                            class="inline-flex min-h-11 items-center rounded-xl px-2 text-xs font-semibold whitespace-nowrap text-primary hover:bg-primary hover:text-white lg:min-h-0 lg:w-fit lg:border lg:border-stone-200 lg:bg-butter lg:px-3 lg:py-1.5 lg:text-zinc-950"
                        >
                            Clear all
                        </Link>
                    </div>

                    <div
                        class="flex min-w-0 flex-1 gap-2 overflow-x-auto overscroll-x-contain lg:mt-4 lg:flex-wrap lg:overflow-visible"
                    >
                        <template
                            v-for="filter in activeFilterChips"
                            :key="filter.key"
                        >
                            <span
                                class="inline-flex shrink-0 items-center gap-1 rounded-xl border border-stone-200 bg-butter pl-2 text-xs font-semibold text-zinc-800 lg:gap-2 lg:px-3 lg:py-1.5 lg:text-sm lg:font-bold"
                            >
                                <span class="text-primary">{{
                                    filter.label
                                }}</span>
                                <span
                                    class="max-w-40 truncate lg:max-w-none"
                                    :title="filter.value"
                                    >{{ filter.value }}</span
                                >
                                <Link
                                    :href="
                                        home({
                                            query: queryWithoutFilter(
                                                filter.key,
                                            ),
                                        })
                                    "
                                    preserve-scroll
                                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl text-zinc-700 hover:bg-primary hover:text-white lg:border lg:border-stone-200 lg:bg-white"
                                    :aria-label="`Clear ${filter.label} filter`"
                                >
                                    <AppIcon
                                        name="fa-solid fa-xmark"
                                        class="text-[10px]"
                                    />
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
        </PullToRefresh>

        <Modal
            :show="activeModal === 'post'"
            mobile-fullscreen
            title="Add a memory"
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
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import EditPostForm from '@/components/EditPostForm.vue';
import type { EditableTimelinePost } from '@/components/EditPostForm.vue';
import Feed from '@/components/Feed.vue';
import Modal from '@/components/Modal.vue';
import PostForm from '@/components/PostForm.vue';
import PullToRefresh from '@/components/PullToRefresh.vue';
import Search from '@/components/Search.vue';
import Sidebar from '@/components/Sidebar.vue';
import BaseLayout from '@/layouts/BaseLayout.vue';
import { gallery, home } from '@/routes';
import type {
    PageProps,
    PaginatedTimelinePosts,
    TimelineFilters as ActiveTimelineFilters,
    TimelinePost,
    TimelinePostType,
} from '@/types';

const page = usePage<PageProps>();
const timeline = computed(() => page.props.timeline);

type ActiveModal = 'post' | 'search' | 'edit' | null;

const activeModal = ref<ActiveModal>(null);
const selectedEditablePost = ref<EditableTimelinePost | null>(null);

const props = defineProps<{
    timelinePosts: PaginatedTimelinePosts;
    filters: ActiveTimelineFilters;
    postTypes: TimelinePostType[];
}>();

const refreshTimeline = () =>
    new Promise<void>((resolve, reject) => {
        router.get(
            home({ query: { ...props.filters } }),
            {},
            {
                only: ['timelinePosts'],
                reset: ['timelinePosts'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => resolve(),
                onError: () => reject(new Error('Timeline refresh failed')),
                onCancel: () => reject(new Error('Timeline refresh cancelled')),
                onNetworkError: () => {
                    reject(new Error('Timeline refresh failed'));

                    return false;
                },
                onFinish: () =>
                    reject(new Error('Timeline refresh did not complete')),
            },
        );
    });

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
