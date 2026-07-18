<template>
    <aside
        class="relative grid h-fit gap-3 border-b-4 border-zinc-950 bg-white p-3 lg:sticky lg:top-0 lg:h-dvh lg:min-h-0 lg:content-start lg:gap-5 lg:overflow-y-auto lg:overscroll-contain lg:border-r-4 lg:p-5"
    >
        <!--
            WEBSITE HEADER TITLE WITH HOMEPAGE LINK
        -->
        <section
            class="flex items-center justify-between gap-4 px-2 py-2 lg:border-b-3 lg:border-zinc-950 lg:px-1 lg:pt-1 lg:pb-5"
        >
            <Link
                :href="home()"
                class="flex flex-col gap-0.5 text-left text-base font-semibold tracking-normal focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-[#77c8b5]"
            >
                <span
                    class="story-font text-4xl leading-none font-bold text-[#e06573] lg:text-5xl"
                    >Remember</span
                >
                <span
                    class="text-xs font-black tracking-[0.2em] text-zinc-700 uppercase"
                    >Timeline</span
                >
            </Link>
            <span
                class="hidden -rotate-2 border-2 border-zinc-950 bg-[#f4ce63] px-2 py-1 text-[0.65rem] font-black tracking-wider uppercase shadow-[2px_2px_0_#18181b] lg:inline-flex"
            >
                Shared archive
            </span>
            <Link
                :href="profileEdit()"
                class="flex size-11 shrink-0 items-center justify-center border-3 border-zinc-950 bg-[#77c8b5] text-sm font-black text-zinc-950 shadow-[3px_3px_0_#18181b] lg:hidden"
                aria-label="Open profile"
            >
                {{ userInitials }}
            </Link>
        </section>

        <section class="hidden grid-cols-2 gap-3 lg:grid">
            <button
                type="button"
                class="col-span-2 flex cursor-pointer items-center justify-center gap-2 border-3 border-zinc-950 bg-[#e06573] px-4 py-3 text-sm font-black text-white shadow-[4px_4px_0_#18181b] transition hover:-translate-y-0.5 hover:shadow-[6px_6px_0_#18181b]"
                @click="emit('openPostForm')"
            >
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Preserve a memory
            </button>
            <button
                type="button"
                class="col-span-2 flex cursor-pointer items-center justify-center gap-2 border-3 border-zinc-950 bg-[#f4ce63] px-4 py-2.5 text-sm font-black text-zinc-950 shadow-[3px_3px_0_#18181b] transition hover:-translate-y-0.5 hover:bg-[#77c8b5]"
                @click="emit('openSearch')"
            >
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                Search the archive
            </button>
        </section>

        <!--
            NAVIGATION
        -->
        <section
            class="hidden border-3 border-zinc-950 bg-white p-3 shadow-[4px_4px_0_#77c8b5] lg:block"
        >
            <p
                class="mb-2 px-2 text-[0.65rem] font-black tracking-[0.2em] text-[#c84f65] uppercase"
            >
                Browse
            </p>
            <nav aria-label="Primary navigation">
                <ul class="grid gap-1">
                    <NavigationLink
                        icon="fa-solid fa-house"
                        label="Timeline"
                        :link="home()"
                        :active="isTimeline && filters.type === ''"
                    />
                    <NavigationLink
                        icon="fa-solid fa-camera-retro"
                        label="Memories"
                        :link="home({ query: queryForType('Memory') })"
                        :active="isTimeline && filters.type === 'Memory'"
                    />
                    <NavigationLink
                        icon="fa-regular fa-star"
                        label="Milestones"
                        :link="home({ query: queryForType('Milestone') })"
                        :active="isTimeline && filters.type === 'Milestone'"
                    />
                    <NavigationLink
                        icon="fa-regular fa-calendar"
                        label="Events"
                        :link="home({ query: queryForType('Event') })"
                        :active="isTimeline && filters.type === 'Event'"
                    />
                    <NavigationLink
                        icon="fa-regular fa-envelope"
                        label="Letters"
                        :link="home({ query: queryForType('Letter') })"
                        :active="isTimeline && filters.type === 'Letter'"
                    />
                    <NavigationLink
                        icon="fa-solid fa-images"
                        label="Gallery"
                        :link="gallery()"
                        :active="isGallery"
                    />
                    <li class="mt-2 border-t-2 border-zinc-950 pt-2">
                        <Link
                            :href="profileEdit()"
                            class="flex items-center gap-3 border-3 px-3 py-2.5 text-sm font-black transition"
                            :class="
                                isProfile
                                    ? 'border-zinc-950 bg-[#77c8b5] shadow-[3px_3px_0_#18181b]'
                                    : 'border-transparent text-zinc-700 hover:border-zinc-950 hover:bg-[#fff0b8]'
                            "
                            :aria-current="isProfile ? 'page' : undefined"
                        >
                            <span
                                class="flex size-8 items-center justify-center border-2 border-zinc-950 bg-[#77c8b5] text-xs font-black text-zinc-950"
                            >
                                {{ userInitials }}
                            </span>
                            <span>Profile</span>
                        </Link>
                    </li>
                    <li>
                        <Link
                            :href="logout()"
                            method="post"
                            as="button"
                            class="flex w-full cursor-pointer items-center gap-3 border-3 border-transparent px-3 py-2.5 text-left text-sm font-black text-zinc-700 transition hover:border-zinc-950 hover:bg-[#e06573] hover:text-white focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#77c8b5]"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center border-2 border-zinc-950 bg-white text-zinc-950"
                                aria-hidden="true"
                            >
                                <i
                                    class="fa-solid fa-arrow-right-from-bracket"
                                ></i>
                            </span>
                            <span>Log out</span>
                        </Link>
                    </li>
                </ul>
            </nav>
        </section>

        <!--
            MEMORIES COUNTER
        -->
        <section
            class="hidden border-3 border-zinc-950 bg-[#fff0b8] p-3 shadow-[4px_4px_0_#f4ce63] lg:block"
        >
            <h2
                class="mb-3 text-[0.65rem] font-black tracking-[0.2em] text-zinc-950 uppercase"
            >
                Archive at a glance
            </h2>
            <div
                class="grid grid-cols-3 divide-x-2 divide-zinc-950 border-2 border-zinc-950 bg-white"
            >
                <div class="grid gap-0.5 p-2 text-center">
                    <strong class="text-xl leading-none font-black">{{
                        memories_count
                    }}</strong>
                    <span
                        class="text-[0.6rem] font-black tracking-wide text-zinc-600 uppercase"
                        >Memories</span
                    >
                </div>
                <div class="grid gap-0.5 p-2 text-center">
                    <strong class="text-xl leading-none font-black">{{
                        media_count
                    }}</strong>
                    <span
                        class="text-[0.6rem] font-black tracking-wide text-zinc-600 uppercase"
                        >Media</span
                    >
                </div>
                <div class="grid gap-0.5 p-2 text-center">
                    <strong class="text-xl leading-none font-black">{{
                        family_count
                    }}</strong>
                    <span
                        class="text-[0.6rem] font-black tracking-wide text-zinc-600 uppercase"
                        >Family</span
                    >
                </div>
            </div>
        </section>

        <!--
            FAMILY SECTION
        -->
        <section
            class="hidden border-3 border-zinc-950 bg-white p-3 shadow-[4px_4px_0_#e06573] lg:block"
        >
            <h2
                class="text-[0.65rem] font-black tracking-[0.2em] text-[#c84f65] uppercase"
            >
                Sharing these memories
            </h2>

            <div class="mt-3 grid gap-2">
                <div
                    v-for="member in family"
                    :key="member.name"
                    class="flex min-w-0 items-center gap-3 border-2 border-transparent p-1.5 hover:border-zinc-950 hover:bg-[#fff0b8]"
                >
                    <img
                        v-if="member.photo"
                        :src="member.photo"
                        class="size-10 shrink-0 border-2 border-zinc-950 object-cover"
                        :alt="`${member.name}'s profile image`"
                    />
                    <div
                        v-else
                        class="flex size-10 shrink-0 items-center justify-center border-2 border-zinc-950 bg-[#77c8b5] text-sm font-black text-zinc-950"
                        aria-hidden="true"
                    >
                        {{ makeUserInitials(member.name) }}
                    </div>
                    <div class="min-w-0 text-sm leading-tight">
                        <p class="truncate font-black text-zinc-950">
                            {{ member.name }}
                        </p>
                        <p class="truncate text-xs font-semibold text-zinc-500">
                            {{ member.username }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!--
            TAGS SECTION
        -->
        <section
            v-if="tags.length"
            class="hidden border-t-3 border-zinc-950 pt-4 lg:block"
        >
            <h2
                class="text-xs font-black tracking-[0.2em] text-[#c84f65] uppercase"
            >
                Tags
            </h2>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <Link
                    v-for="tag in tags"
                    :key="tag"
                    :href="home({ query: queryForTag(tag) })"
                    class="border-2 px-2 py-1 text-xs font-bold transition hover:border-zinc-950 hover:bg-[#fff0b8]"
                    :class="
                        filters.tag === tag
                            ? 'border-zinc-950 bg-[#f4ce63] text-zinc-950'
                            : 'border-transparent text-zinc-600 hover:text-[#e06573]'
                    "
                >
                    #{{ tag }}
                </Link>
            </div>
        </section>

        <!--
            FOOTER SECTION
        -->
        <div
            class="hidden items-center justify-between gap-3 border-t-3 border-zinc-950 pt-4 lg:flex"
        >
            <small class="font-bold text-zinc-500">v{{ version }}</small>
            <small class="font-black text-[#c84f65]">Remember</small>
        </div>
    </aside>

    <div class="lg:hidden">
        <!--
            FILTERS COUNTER (MOBILE)
        -->
        <section class="flex flex-col items-center px-2 py-2 lg:hidden">
            <div class="mt-3 flex flex-wrap gap-1">
                <Link
                    :href="home({ query: queryForType('') })"
                    class="border-3 border-zinc-950 px-3 py-2 text-sm font-black shadow-[3px_3px_0_0_#18181b]"
                    :class="
                        filters.type === ''
                            ? 'bg-[#e06573] text-white'
                            : 'bg-white text-zinc-700 hover:bg-[#fff0b8]'
                    "
                >
                    All
                </Link>
                <Link
                    v-for="type in postTypes"
                    :key="type"
                    :href="home({ query: queryForType(type) })"
                    class="border-3 border-zinc-950 px-3 py-2 text-sm font-black shadow-[3px_3px_0_0_#18181b]"
                    :class="
                        filters.type === type
                            ? 'bg-[#e06573] text-white'
                            : 'bg-white text-zinc-700 hover:bg-[#fff0b8]'
                    "
                >
                    {{ type }}
                </Link>
            </div>
        </section>

        <!--
            ADD NEW MEMORY SECTION (MOBILE)
        -->
        <section class="flex flex-col px-5 pt-4 lg:hidden">
            <button
                type="button"
                class="flex cursor-pointer flex-row items-center justify-center gap-2 border-3 border-zinc-950 bg-[#e06573] px-3 py-2 font-black text-white shadow-[4px_4px_0_0_#18181b]"
                @click="emit('openPostForm')"
            >
                <i class="fa-solid fa-plus"></i>
                <span>New Memory</span>
            </button>
        </section>
    </div>

    <!--
        MOBILE ONLY NAVIGATION
    -->
    <div
        class="fixed right-0 bottom-0 left-0 z-50 h-[calc(5rem+env(safe-area-inset-bottom))] border-t-4 border-zinc-950 bg-white pr-[max(0.75rem,env(safe-area-inset-right))] pb-[env(safe-area-inset-bottom)] pl-[max(0.75rem,env(safe-area-inset-left))] lg:hidden"
    >
        <div class="mx-auto grid h-20 max-w-lg grid-cols-5 py-2 font-medium">
            <MobileNavigationLink
                icon="fa-solid fa-house"
                label="Timeline"
                :link="home()"
            />
            <MobileNavigationLink
                icon="fa-solid fa-magnifying-glass"
                label="Search"
                @activate="emit('openSearch')"
            />
            <MobileNavigationLink
                icon="fa-solid fa-plus"
                label="New Memory"
                :cta="true"
                @activate="emit('openPostForm')"
            />
            <MobileNavigationLink
                icon="fa-solid fa-images"
                label="Gallery"
                :link="gallery()"
            />
            <MobileNavigationLink
                icon="fa-solid fa-arrow-right-from-bracket"
                label="Logout"
                :link="logout()"
                :post="true"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MobileNavigationLink from '@/components/MobileNavigationLink.vue';
import NavigationLink from '@/components/NavigationLink.vue';
import { gallery, home, logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import type { PageProps, TimelinePostType } from '@/types';

const emit = defineEmits<{
    openPostForm: [];
    openSearch: [];
}>();

const page = usePage<PageProps>();

const version = computed(() => page.props.version);
const family = computed(() => page.props.family);
const filters = computed(() => page.props.filters);
const tags = computed(() => page.props.tags);
const family_count = computed(() => page.props.family_count);
const media_count = computed(() => page.props.media_count);
const memories_count = computed(() => page.props.memories_count);
const postTypes = computed(() => page.props.postTypes);
const currentPath = computed(() => page.url.split('?')[0]);
const isGallery = computed(() => currentPath.value === '/gallery');
const isProfile = computed(() => currentPath.value.startsWith('/profile'));
const isTimeline = computed(() => !isGallery.value && !isProfile.value);
const userInitials = computed(() =>
    makeUserInitials(page.props.auth.user?.name ?? ''),
);

const queryForType = (type: TimelinePostType | '') => ({
    search: filters.value.search || undefined,
    tag: filters.value.tag || undefined,
    type: type || undefined,
    author: filters.value.author || undefined,
});

const queryForTag = (tag: string) => ({
    search: filters.value.search || undefined,
    tag: filters.value.tag === tag ? undefined : tag,
    type: filters.value.type || undefined,
    author: filters.value.author || undefined,
});

const makeUserInitials = (name: string) => {
    return name
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
};
</script>
