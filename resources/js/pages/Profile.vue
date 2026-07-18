<template>
    <BaseLayout>
        <template #sidebar>
            <Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
            />
        </template>

        <Head title="Profile" />

        <section class="grid gap-8 pb-24 md:pb-0">
            <header
                class="relative overflow-hidden border-4 border-zinc-950 bg-[#e06573] p-5 shadow-[8px_8px_0_#18181b] sm:p-8"
            >
                <div
                    class="absolute -right-8 -bottom-12 size-36 rotate-12 border-4 border-zinc-950 bg-[#77c8b5] sm:size-44"
                    aria-hidden="true"
                ></div>

                <p
                    class="relative z-10 inline-flex border-3 border-zinc-950 bg-[#f4ce63] px-3 py-1 text-xs font-black tracking-[0.2em] text-zinc-950 uppercase shadow-[3px_3px_0_#18181b]"
                >
                    Your profile
                </p>

                <div
                    class="relative z-10 mt-6 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div class="flex min-w-0 items-center gap-4">
                        <div
                            class="flex size-18 shrink-0 -rotate-2 items-center justify-center border-4 border-zinc-950 bg-[#77c8b5] text-2xl font-black text-zinc-950 shadow-[5px_5px_0_#18181b] sm:size-24 sm:text-3xl"
                            aria-hidden="true"
                        >
                            {{ initials }}
                        </div>
                        <div class="min-w-0">
                            <h1
                                class="text-3xl leading-none font-black tracking-tight break-words text-white sm:text-5xl"
                            >
                                {{ user.name }}
                            </h1>
                            <p
                                class="mt-3 w-fit max-w-full truncate border-2 border-zinc-950 bg-white px-2 py-1 text-sm font-bold text-zinc-950"
                            >
                                {{ user.email }}
                            </p>
                        </div>
                    </div>

                    <Link
                        :href="logout()"
                        method="post"
                        as="button"
                        class="inline-flex w-fit cursor-pointer items-center gap-2 border-3 border-zinc-950 bg-white px-4 py-2.5 text-sm font-black text-zinc-950 shadow-[4px_4px_0_#18181b] transition hover:-translate-y-0.5 hover:shadow-[6px_6px_0_#18181b] focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-white"
                    >
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        Log out
                    </Link>
                </div>
            </header>

            <section class="grid items-start gap-6 xl:grid-cols-2">
                <article
                    class="border-4 border-zinc-950 bg-white shadow-[7px_7px_0_#77c8b5]"
                >
                    <div
                        class="flex items-start gap-4 border-b-3 border-zinc-950 bg-[#f7f1e7] p-5"
                    >
                        <div
                            class="flex size-11 shrink-0 -rotate-2 items-center justify-center border-3 border-zinc-950 bg-[#77c8b5] text-lg shadow-[3px_3px_0_#18181b]"
                            aria-hidden="true"
                        >
                            <i class="fa-solid fa-address-card"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-zinc-950">
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
                            class="grid gap-2 text-sm font-black text-zinc-950"
                        >
                            Name
                            <input
                                name="name"
                                type="text"
                                autocomplete="name"
                                required
                                :value="user.name"
                                class="border-3 border-zinc-950 bg-white px-3 py-2.5 text-base font-semibold transition outline-none focus:bg-[#fff9e8] focus:shadow-[4px_4px_0_#e06573]"
                            />
                            <span
                                v-if="errors.name"
                                class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700"
                            >
                                {{ errors.name }}
                            </span>
                        </label>

                        <label
                            class="grid gap-2 text-sm font-black text-zinc-950"
                        >
                            Email
                            <input
                                name="email"
                                type="email"
                                autocomplete="email"
                                required
                                :value="user.email"
                                class="border-3 border-zinc-950 bg-white px-3 py-2.5 text-base font-semibold transition outline-none focus:bg-[#fff9e8] focus:shadow-[4px_4px_0_#e06573]"
                            />
                            <span
                                v-if="errors.email"
                                class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700"
                            >
                                {{ errors.email }}
                            </span>
                        </label>

                        <div class="flex flex-wrap items-center gap-4 pt-1">
                            <button
                                type="submit"
                                :disabled="processing"
                                class="cursor-pointer border-3 border-zinc-950 bg-[#e06573] px-4 py-2.5 text-sm font-black text-white shadow-[4px_4px_0_#18181b] transition hover:-translate-y-0.5 hover:shadow-[6px_6px_0_#18181b] disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:shadow-[4px_4px_0_#18181b]"
                            >
                                {{ processing ? 'Saving...' : 'Save details' }}
                            </button>
                            <p
                                v-if="wasSuccessful"
                                role="status"
                                class="border-2 border-zinc-950 bg-[#77c8b5] px-3 py-1.5 text-sm font-black text-zinc-950"
                            >
                                Saved.
                            </p>
                        </div>
                    </Form>
                </article>

                <article
                    class="border-4 border-zinc-950 bg-white shadow-[7px_7px_0_#f4ce63]"
                >
                    <div
                        class="flex items-start gap-4 border-b-3 border-zinc-950 bg-[#f7f1e7] p-5"
                    >
                        <div
                            class="flex size-11 shrink-0 rotate-2 items-center justify-center border-3 border-zinc-950 bg-[#f4ce63] text-lg shadow-[3px_3px_0_#18181b]"
                            aria-hidden="true"
                        >
                            <i class="fa-solid fa-key"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-zinc-950">
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
                            class="grid gap-2 text-sm font-black text-zinc-950"
                        >
                            Current password
                            <input
                                name="current_password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="border-3 border-zinc-950 bg-white px-3 py-2.5 text-base font-semibold transition outline-none focus:bg-[#fff9e8] focus:shadow-[4px_4px_0_#e06573]"
                            />
                            <span
                                v-if="errors.current_password"
                                class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700"
                            >
                                {{ errors.current_password }}
                            </span>
                        </label>

                        <label
                            class="grid gap-2 text-sm font-black text-zinc-950"
                        >
                            New password
                            <input
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="border-3 border-zinc-950 bg-white px-3 py-2.5 text-base font-semibold transition outline-none focus:bg-[#fff9e8] focus:shadow-[4px_4px_0_#e06573]"
                            />
                            <span
                                v-if="errors.password"
                                class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700"
                            >
                                {{ errors.password }}
                            </span>
                        </label>

                        <label
                            class="grid gap-2 text-sm font-black text-zinc-950"
                        >
                            Confirm password
                            <input
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="border-3 border-zinc-950 bg-white px-3 py-2.5 text-base font-semibold transition outline-none focus:bg-[#fff9e8] focus:shadow-[4px_4px_0_#e06573]"
                            />
                            <span
                                v-if="errors.password_confirmation"
                                class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700"
                            >
                                {{ errors.password_confirmation }}
                            </span>
                        </label>

                        <div class="flex flex-wrap items-center gap-4 pt-1">
                            <button
                                type="submit"
                                :disabled="processing"
                                class="cursor-pointer border-3 border-zinc-950 bg-[#e06573] px-4 py-2.5 text-sm font-black text-white shadow-[4px_4px_0_#18181b] transition hover:-translate-y-0.5 hover:shadow-[6px_6px_0_#18181b] disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:shadow-[4px_4px_0_#18181b]"
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
                                class="border-2 border-zinc-950 bg-[#77c8b5] px-3 py-1.5 text-sm font-black text-zinc-950"
                            >
                                Updated.
                            </p>
                        </div>
                    </Form>
                </article>
            </section>
        </section>

        <Modal
            :show="activeModal === 'post'"
            title="Preserve a new memory"
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
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Modal from '@/components/Modal.vue';
import PostForm from '@/components/PostForm.vue';
import Search from '@/components/Search.vue';
import Sidebar from '@/components/Sidebar.vue';
import BaseLayout from '@/layouts/BaseLayout.vue';
import { logout } from '@/routes';
import { update as updatePassword } from '@/routes/user-password';
import { update as updateProfile } from '@/routes/user-profile-information';
import type { PageProps } from '@/types';

type ActiveModal = 'post' | 'search' | null;

const page = usePage<PageProps>();
const activeModal = ref<ActiveModal>(null);

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
