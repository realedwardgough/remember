<template>
    <aside
        class="border-b bg-white px-5 py-4 lg:sticky lg:top-0 lg:flex lg:h-dvh lg:flex-col lg:overflow-y-auto lg:border-r lg:border-b-0 lg:px-5 lg:py-8"
    >
        <div class="flex items-center justify-between gap-3 lg:mb-9">
            <Link :href="home()" class="min-w-0">
                <span class="story-font text-3xl"
                    >Remember <span class="text-rose-500">♡</span></span
                >
                <span class="mt-1 block truncate text-xs text-stone-500">{{
                    timeline.name
                }}</span>
            </Link>
            <Link
                :href="profileEdit()"
                aria-label="Open account"
                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-lilac text-sm font-semibold text-primary lg:hidden"
                >{{ userInitials }}</Link
            >
        </div>
        <button
            type="button"
            class="button-primary mb-6 hidden w-full lg:flex"
            @click="emit('openPostForm')"
        >
            <Plus :size="18" /> Add a memory
        </button>
        <nav aria-label="Primary navigation" class="hidden lg:block">
            <ul class="grid gap-1">
                <NavigationLink
                    icon="fa-solid fa-house"
                    label="Timeline"
                    :link="home()"
                    :active="isTimeline && !filters.type"
                />
                <NavigationLink
                    v-for="type in postTypes"
                    :key="type"
                    :icon="typeIcons[type]"
                    :label="typeLabels[type]"
                    :link="home({ query: queryForType(type) })"
                    :active="isTimeline && filters.type === type"
                />
                <NavigationLink
                    icon="fa-solid fa-images"
                    label="Photos"
                    :link="gallery()"
                    :active="isGallery"
                />
                <NavigationLink
                    icon="fa-regular fa-comment"
                    label="Messages"
                    :link="messagesIndex()"
                    :active="isMessages"
                    :badge="page.props.unreadMessagesCount"
                />
                <li>
                    <button
                        class="flex min-h-12 w-full items-center gap-3 rounded-xl px-3 text-sm text-stone-600 hover:bg-lilac"
                        @click="emit('openSearch')"
                    >
                        <Search :size="19" /> Search
                    </button>
                </li>
            </ul>
        </nav>
        <section v-if="tags.length" class="mt-7 hidden border-t pt-5 lg:block">
            <h2 class="mb-3 text-xs font-semibold text-stone-500">
                Little collections
            </h2>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="tag in tags"
                    :key="tag"
                    :href="home({ query: queryForTag(tag) })"
                    class="rounded-full px-3 py-2 text-xs"
                    :class="
                        filters.tag === tag
                            ? 'bg-primary text-white'
                            : 'bg-canvas text-stone-600'
                    "
                    >#{{ tag }}</Link
                >
            </div>
        </section>
        <div class="mt-auto hidden pt-8 lg:block">
            <Link
                :href="profileEdit()"
                class="flex min-h-12 items-center gap-3 rounded-xl px-3 py-2 text-sm"
                :class="isProfile ? 'bg-lilac text-primary' : 'hover:bg-canvas'"
            >
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-lilac font-semibold text-primary"
                    >{{ userInitials }}</span
                >
                <span>Account & family</span>
            </Link>
            <p class="mt-5 px-3 text-xs text-stone-400">
                Your family. Your memories.
            </p>
        </div>
    </aside>
    <nav
        aria-label="Mobile navigation"
        class="fixed inset-x-0 bottom-0 z-40 border-t bg-white/95 pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] pl-[env(safe-area-inset-left)] backdrop-blur-lg lg:hidden"
    >
        <div class="mx-auto grid h-18 max-w-lg grid-cols-5 items-center px-2">
            <MobileNavigationLink
                icon="fa-solid fa-house"
                label="Timeline"
                :link="home()"
                :active="isTimeline"
            />
            <MobileNavigationLink
                icon="fa-solid fa-magnifying-glass"
                label="Search"
                @activate="emit('openSearch')"
            />
            <MobileNavigationLink
                icon="fa-solid fa-plus"
                label="Add"
                cta
                @activate="emit('openPostForm')"
            />
            <MobileNavigationLink
                icon="fa-regular fa-comment"
                label="Messages"
                :link="messagesIndex()"
                :active="isMessages"
                :badge="page.props.unreadMessagesCount"
            />
            <MobileNavigationLink
                icon="fa-regular fa-user"
                label="Account"
                :link="profileEdit()"
                :active="isProfile"
            />
        </div>
    </nav>
</template>
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import MobileNavigationLink from '@/components/MobileNavigationLink.vue';
import NavigationLink from '@/components/NavigationLink.vue';
import { gallery, home } from '@/routes';
import { index as messagesIndex } from '@/routes/messages';
import { edit as profileEdit } from '@/routes/profile';
import type { PageProps, TimelinePostType } from '@/types';
const emit = defineEmits<{ openPostForm: []; openSearch: [] }>();
const page = usePage<PageProps>();
const timeline = computed(() => page.props.timeline);
const filters = computed(() => page.props.filters);
const tags = computed(() => page.props.tags);
const postTypes = computed(() => page.props.postTypes);
const currentPath = computed(() => page.url.split('?')[0]);
const isGallery = computed(() => currentPath.value === '/gallery');
const isMessages = computed(() => currentPath.value.startsWith('/messages'));
const isProfile = computed(
    () =>
        currentPath.value.startsWith('/profile') ||
        currentPath.value.startsWith('/timeline'),
);
const isTimeline = computed(() => currentPath.value === '/');
const userInitials = computed(() =>
    (page.props.auth.user?.name ?? '')
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);
const typeLabels = {
    Memory: 'Memories',
    Letter: 'Letters',
    Milestone: 'Milestones',
    Event: 'Events',
};
const typeIcons = {
    Memory: 'fa-regular fa-image',
    Letter: 'fa-regular fa-envelope',
    Milestone: 'fa-regular fa-star',
    Event: 'fa-regular fa-calendar',
};
const queryForType = (type: TimelinePostType | '') => ({
    ...filters.value,
    type: type || undefined,
});
const queryForTag = (tag: string) => ({
    ...filters.value,
    tag: filters.value.tag === tag ? undefined : tag,
});
</script>
