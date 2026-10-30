<template>
    <BaseLayout>
        <template #sidebar>
            <Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
            />
        </template>

        <Head title="Profile" />

        <section class="grid gap-6">
            <PageHeader
                :title="user.name"
                eyebrow="Your account"
                :description="user.email"
            >
                <span
                    class="flex size-14 items-center justify-center rounded-full bg-lilac text-xl font-semibold text-primary"
                    >{{ initials }}</span
                >
                <button
                    type="button"
                    class="button-secondary"
                    :disabled="loggingOut"
                    @click="logoutAccount"
                >
                    {{ loggingOut ? 'Logging out…' : 'Log out' }}
                </button>
            </PageHeader>

            <Link
                :href="gallery()"
                class="surface flex min-h-16 items-center gap-3 p-5 lg:hidden"
            >
                <AppIcon name="fa-solid fa-images" />
                <span class="font-semibold">Family photos</span>
                <AppIcon name="fa-solid fa-arrow-right" class="ml-auto" />
            </Link>

            <Link
                v-if="canManageTimeline"
                :href="TimelineManagementController()"
                class="flex items-center justify-between gap-4 rounded-xl border border-stone-200 bg-butter p-5 text-zinc-950 transition"
            >
                <span>
                    <strong class="block text-lg font-semibold"
                        >Manage timeline</strong
                    >
                    <span class="mt-1 block text-sm font-semibold"
                        >Update details, invite members, and manage roles.</span
                    >
                </span>
                <AppIcon
                    name="fa-solid fa-arrow-right"
                    class="text-xl"
                    aria-hidden="true"
                />
            </Link>

            <NotificationSettings />

            <section class="grid items-start gap-6 xl:grid-cols-2">
                <article class="surface overflow-hidden">
                    <div
                        class="flex items-start gap-4 border-b border-stone-200 bg-canvas p-5"
                    >
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-stone-200 bg-mint text-lg"
                            aria-hidden="true"
                        >
                            <AppIcon name="fa-solid fa-address-card" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-zinc-950">
                                Account details
                            </h2>
                            <p
                                class="mt-1 text-sm leading-5 font-medium text-zinc-700"
                            >
                                Keep your timeline identity up to date.
                            </p>
                        </div>
                    </div>

                    <Form
                        v-bind="updateProfile.form.put()"
                        v-slot="{ errors, processing, wasSuccessful }"
                        class="grid gap-5 p-5 sm:p-6"
                    >
                        <label
                            class="grid gap-2 text-sm font-semibold text-zinc-950"
                        >
                            Name
                            <input
                                name="name"
                                type="text"
                                autocomplete="name"
                                required
                                :value="user.name"
                                class="rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-base font-semibold transition focus:bg-canvas"
                            />
                            <span
                                v-if="errors.name"
                                class="border-l border-red-600 pl-2 text-sm font-bold text-red-700"
                            >
                                {{ errors.name }}
                            </span>
                        </label>

                        <label
                            class="grid gap-2 text-sm font-semibold text-zinc-950"
                        >
                            Email
                            <input
                                name="email"
                                type="email"
                                autocomplete="email"
                                required
                                :value="user.email"
                                class="rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-base font-semibold transition focus:bg-canvas"
                            />
                            <span
                                v-if="errors.email"
                                class="border-l border-red-600 pl-2 text-sm font-bold text-red-700"
                            >
                                {{ errors.email }}
                            </span>
                        </label>

                        <div class="flex flex-wrap items-center gap-4 pt-1">
                            <button
                                type="submit"
                                :disabled="processing"
                                class="cursor-pointer rounded-xl border border-stone-200 bg-primary px-4 py-2.5 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
                            >
                                {{ processing ? 'Saving...' : 'Save details' }}
                            </button>
                            <p
                                v-if="wasSuccessful"
                                role="status"
                                class="rounded-xl border border-stone-200 bg-mint px-3 py-1.5 text-sm font-semibold text-zinc-950"
                            >
                                Saved.
                            </p>
                        </div>
                    </Form>
                </article>

                <article class="surface overflow-hidden">
                    <div
                        class="flex items-start gap-4 border-b border-stone-200 bg-canvas p-5"
                    >
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-stone-200 bg-butter text-lg"
                            aria-hidden="true"
                        >
                            <AppIcon name="fa-solid fa-key" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-zinc-950">
                                Password
                            </h2>
                            <p
                                class="mt-1 text-sm leading-5 font-medium text-zinc-700"
                            >
                                Use your current password to choose a new one.
                            </p>
                        </div>
                    </div>

                    <Form
                        v-bind="updatePassword.form.put()"
                        :reset-on-success="[
                            'current_password',
                            'password',
                            'password_confirmation',
                        ]"
                        v-slot="{ errors, processing, wasSuccessful }"
                        class="grid gap-5 p-5 sm:p-6"
                    >
                        <label
                            class="grid gap-2 text-sm font-semibold text-zinc-950"
                        >
                            Current password
                            <input
                                name="current_password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-base font-semibold transition focus:bg-canvas"
                            />
                            <span
                                v-if="errors.current_password"
                                class="border-l border-red-600 pl-2 text-sm font-bold text-red-700"
                            >
                                {{ errors.current_password }}
                            </span>
                        </label>

                        <label
                            class="grid gap-2 text-sm font-semibold text-zinc-950"
                        >
                            New password
                            <input
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-base font-semibold transition focus:bg-canvas"
                            />
                            <span
                                v-if="errors.password"
                                class="border-l border-red-600 pl-2 text-sm font-bold text-red-700"
                            >
                                {{ errors.password }}
                            </span>
                        </label>

                        <label
                            class="grid gap-2 text-sm font-semibold text-zinc-950"
                        >
                            Confirm password
                            <input
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-base font-semibold transition focus:bg-canvas"
                            />
                            <span
                                v-if="errors.password_confirmation"
                                class="border-l border-red-600 pl-2 text-sm font-bold text-red-700"
                            >
                                {{ errors.password_confirmation }}
                            </span>
                        </label>

                        <div class="flex flex-wrap items-center gap-4 pt-1">
                            <button
                                type="submit"
                                :disabled="processing"
                                class="cursor-pointer rounded-xl border border-stone-200 bg-primary px-4 py-2.5 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
                            >
                                {{
                                    processing
                                        ? 'Updating...'
                                        : 'Update password'
                                }}
                            </button>
                            <p
                                v-if="wasSuccessful"
                                role="status"
                                class="rounded-xl border border-stone-200 bg-mint px-3 py-1.5 text-sm font-semibold text-zinc-950"
                            >
                                Updated.
                            </p>
                        </div>
                    </Form>
                </article>
            </section>
            <section
                v-if="family.length"
                class="surface p-5"
                aria-labelledby="family-heading"
            >
                <h2 id="family-heading" class="story-font text-2xl">
                    Sharing this story
                </h2>
                <p class="mt-1 text-sm text-stone-500">
                    The people making these memories together.
                </p>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <Link
                        v-for="member in family"
                        :key="member.username"
                        :href="home({ query: { author: member.username } })"
                        class="flex min-h-14 items-center gap-3 rounded-xl p-2 hover:bg-lilac"
                    >
                        <img
                            v-if="member.photo"
                            :src="member.photo"
                            :alt="member.name"
                            class="size-10 rounded-full object-cover"
                        />
                        <span
                            v-else
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-mint text-sm font-semibold"
                            >{{ member.name.slice(0, 1) }}</span
                        >
                        <span class="min-w-0"
                            ><span
                                class="block truncate text-sm font-semibold"
                                >{{ member.name }}</span
                            ><span class="block truncate text-xs text-stone-500"
                                >@{{ member.username }}</span
                            ></span
                        >
                    </Link>
                </div>
            </section>
        </section>

        <Modal
            :show="activeModal === 'post'"
            mobile-fullscreen
            title="Add a memory"
            eyebrow="New memory"
            @close="activeModal = null"
        >
            <PostForm @close="activeModal = null" />
        </Modal>

        <Modal
            :show="activeModal === 'search'"
            title="Search the timeline"
            eyebrow="Find memories"
            @close="activeModal = null"
        >
            <Search @close="activeModal = null" />
        </Modal>
    </BaseLayout>
</template>

<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TimelineManagementController from '@/actions/App/Http/Controllers/TimelineManagementController';
import AppIcon from '@/components/AppIcon.vue';
import Modal from '@/components/Modal.vue';
import NotificationSettings from '@/components/NotificationSettings.vue';
import PageHeader from '@/components/PageHeader.vue';
import PostForm from '@/components/PostForm.vue';
import Search from '@/components/Search.vue';
import Sidebar from '@/components/Sidebar.vue';
import BaseLayout from '@/layouts/BaseLayout.vue';
import { clearBrowserPush } from '@/lib/notifications';
import { gallery, home, logout } from '@/routes';
import { update as updatePassword } from '@/routes/user-password';
import { update as updateProfile } from '@/routes/user-profile-information';
import type { PageProps } from '@/types';

defineProps<{ canManageTimeline: boolean }>();

type ActiveModal = 'post' | 'search' | null;

const page = usePage<PageProps>();
const activeModal = ref<ActiveModal>(null);
const loggingOut = ref(false);
async function logoutAccount() {
    if (loggingOut.value) {
        return;
    }

    loggingOut.value = true;
    let timer: ReturnType<typeof setTimeout> | undefined;

    try {
        await Promise.race([
            clearBrowserPush().catch(() => {}),
            new Promise<void>((resolve) => {
                timer = setTimeout(resolve, 2000);
            }),
        ]);
    } finally {
        clearTimeout(timer);
        router.post(
            logout().url,
            {},
            {
                onFinish: () => {
                    loggingOut.value = false;
                },
            },
        );
    }
}

const family = computed(() => Object.values(page.props.family));

const user = computed(() => page.props.auth.user!);

const initials = computed(() => {
    return user.value.name
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
});
</script>
