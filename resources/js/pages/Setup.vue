<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/SetupController';
import AppIcon from '@/components/AppIcon.vue';
</script>

<template>
    <main
        class="relative min-h-screen overflow-hidden bg-canvas px-4 py-8 text-zinc-950 sm:px-8 sm:py-12"
    >
        <Head title="Set up your timeline" />

        <section
            class="relative z-10 mx-auto grid w-full max-w-6xl rounded-xl border border-stone-200 bg-white lg:grid-cols-[0.72fr_1.28fr]"
        >
            <aside
                class="relative overflow-hidden border-b border-stone-200 bg-lilac p-7 sm:p-10 lg:border-r lg:border-b-0 lg:p-12"
            >
                <div class="relative z-10">
                    <p
                        class="story-font text-6xl leading-none font-bold text-stone-800 sm:text-7xl"
                    >
                        Remember
                    </p>
                    <p class="mt-1 text-sm font-semibold tracking-normal">
                        First-time setup
                    </p>
                </div>

                <div class="relative z-10 hidden max-w-md lg:mt-16 lg:block">
                    <span
                        class="inline-flex rounded-xl border border-stone-200 bg-butter px-3 py-1.5 text-xs font-semibold tracking-widest"
                    >
                        Step one
                    </span>
                    <h1 class="page-heading mt-6">Make this timeline yours.</h1>
                    <p class="mt-5 text-base leading-relaxed font-semibold">
                        Name your family timeline and create its first account.
                        You will be signed in as soon as setup is complete.
                    </p>
                </div>
            </aside>

            <div class="p-6 sm:p-10 lg:p-12">
                <div class="mb-8 flex items-start justify-between gap-5">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-normal text-primary"
                        >
                            New installation
                        </p>
                        <h2 class="page-heading mt-2">Create timeline</h2>
                    </div>
                    <span
                        aria-hidden="true"
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-stone-200 bg-butter text-xl"
                    >
                        <AppIcon name="fa-solid fa-wand-magic-sparkles" />
                    </span>
                </div>

                <Form
                    v-bind="store.form()"
                    :reset-on-success="['password', 'password_confirmation']"
                    v-slot="{ errors, processing }"
                    class="grid gap-7"
                >
                    <fieldset class="grid gap-5">
                        <legend
                            class="mb-4 border-b border-stone-200 pb-2 text-sm font-semibold tracking-normal"
                        >
                            Your timeline
                        </legend>

                        <label class="grid gap-2 text-sm font-semibold">
                            Timeline name
                            <input
                                name="timeline_name"
                                type="text"
                                maxlength="100"
                                required
                                autofocus
                                placeholder="The Morgan Family"
                                class="min-h-13 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case placeholder:font-medium placeholder:text-zinc-400 focus:bg-white"
                                :aria-invalid="Boolean(errors.timeline_name)"
                            />
                            <span
                                v-if="errors.timeline_name"
                                class="border-l border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                            >
                                {{ errors.timeline_name }}
                            </span>
                        </label>

                        <label class="grid gap-2 text-sm font-semibold">
                            Short description
                            <textarea
                                name="timeline_description"
                                rows="3"
                                maxlength="280"
                                placeholder="A place for our family stories, milestones and memories."
                                class="resize-y rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case placeholder:font-medium placeholder:text-zinc-400 focus:bg-white"
                                :aria-invalid="
                                    Boolean(errors.timeline_description)
                                "
                            ></textarea>
                            <span
                                v-if="errors.timeline_description"
                                class="border-l border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                            >
                                {{ errors.timeline_description }}
                            </span>
                        </label>
                    </fieldset>

                    <fieldset class="grid gap-5">
                        <legend
                            class="mb-4 border-b border-stone-200 pb-2 text-sm font-semibold tracking-normal"
                        >
                            First account
                        </legend>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="grid gap-2 text-sm font-semibold">
                                Your name
                                <input
                                    name="name"
                                    type="text"
                                    autocomplete="name"
                                    required
                                    placeholder="Alex Morgan"
                                    class="min-h-13 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case placeholder:font-medium placeholder:text-zinc-400 focus:bg-white"
                                    :aria-invalid="Boolean(errors.name)"
                                />
                                <span
                                    v-if="errors.name"
                                    class="border-l border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                    >{{ errors.name }}</span
                                >
                            </label>

                            <label class="grid gap-2 text-sm font-semibold">
                                Username
                                <input
                                    name="username"
                                    type="text"
                                    autocomplete="username"
                                    required
                                    placeholder="alex"
                                    class="min-h-13 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold lowercase placeholder:font-medium placeholder:text-zinc-400 focus:bg-white"
                                    :aria-invalid="Boolean(errors.username)"
                                />
                                <span
                                    v-if="errors.username"
                                    class="border-l border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                    >{{ errors.username }}</span
                                >
                            </label>
                        </div>

                        <label class="grid gap-2 text-sm font-semibold">
                            Email address
                            <input
                                name="email"
                                type="email"
                                autocomplete="email"
                                required
                                placeholder="alex@example.com"
                                class="min-h-13 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case placeholder:font-medium placeholder:text-zinc-400 focus:bg-white"
                                :aria-invalid="Boolean(errors.email)"
                            />
                            <span
                                v-if="errors.email"
                                class="border-l border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                >{{ errors.email }}</span
                            >
                        </label>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="grid gap-2 text-sm font-semibold">
                                Password
                                <input
                                    name="password"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    placeholder="Create a password"
                                    class="min-h-13 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case placeholder:font-medium placeholder:text-zinc-400 focus:bg-white"
                                    :aria-invalid="Boolean(errors.password)"
                                />
                                <span
                                    v-if="errors.password"
                                    class="border-l border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                    >{{ errors.password }}</span
                                >
                            </label>

                            <label class="grid gap-2 text-sm font-semibold">
                                Confirm password
                                <input
                                    name="password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    placeholder="Repeat your password"
                                    class="min-h-13 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case placeholder:font-medium placeholder:text-zinc-400 focus:bg-white"
                                />
                            </label>
                        </div>
                    </fieldset>

                    <button
                        type="submit"
                        :disabled="processing"
                        class="min-h-14 cursor-pointer rounded-xl border border-stone-200 bg-primary px-6 py-3 text-sm font-semibold tracking-wider text-white transition hover:bg-violet-800 focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-primary disabled:cursor-not-allowed disabled:bg-zinc-300 disabled:text-zinc-600"
                    >
                        {{
                            processing
                                ? 'Creating your timeline…'
                                : 'Create timeline and sign in'
                        }}
                    </button>
                </Form>
            </div>
        </section>
    </main>
</template>
