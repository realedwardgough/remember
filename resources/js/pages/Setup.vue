<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/SetupController';
</script>

<template>
    <main
        class="relative min-h-screen overflow-hidden bg-[#fff9e8] px-4 py-8 text-zinc-950 sm:px-8 sm:py-12"
    >
        <Head title="Set up your timeline" />

        <div
            aria-hidden="true"
            class="absolute -top-14 -left-12 size-40 rotate-12 border-4 border-zinc-950 bg-[#f8cc5d] shadow-[7px_7px_0_#18181b]"
        ></div>
        <div
            aria-hidden="true"
            class="absolute top-24 -right-10 size-28 rounded-full border-4 border-zinc-950 bg-[#77c8b5] shadow-[6px_6px_0_#18181b]"
        ></div>

        <section
            class="relative z-10 mx-auto grid w-full max-w-6xl border-4 border-zinc-950 bg-white shadow-[10px_10px_0_#18181b] lg:grid-cols-[0.72fr_1.28fr]"
        >
            <aside
                class="relative overflow-hidden border-b-4 border-zinc-950 bg-[#e06573] p-7 sm:p-10 lg:border-r-4 lg:border-b-0 lg:p-12"
            >
                <div class="relative z-10">
                    <p
                        class="story-font text-6xl leading-none font-bold text-white sm:text-7xl"
                    >
                        Remember
                    </p>
                    <p
                        class="mt-1 text-sm font-black tracking-[0.28em] uppercase"
                    >
                        First-time setup
                    </p>
                </div>

                <div class="relative z-10 mt-14 max-w-md lg:mt-24">
                    <span
                        class="inline-flex -rotate-2 border-3 border-zinc-950 bg-[#f8cc5d] px-3 py-1.5 text-xs font-black tracking-widest uppercase shadow-[4px_4px_0_#18181b]"
                    >
                        Step one
                    </span>
                    <h1
                        class="mt-6 text-4xl leading-[0.95] font-black tracking-[-0.04em] text-white uppercase sm:text-5xl"
                    >
                        Make this timeline yours.
                    </h1>
                    <p class="mt-5 text-base leading-relaxed font-semibold">
                        Name your family timeline and create its first account.
                        You will be signed in as soon as setup is complete.
                    </p>
                </div>

                <div
                    aria-hidden="true"
                    class="absolute -right-14 -bottom-16 size-48 -rotate-12 border-4 border-zinc-950 bg-[#77c8b5]"
                ></div>
            </aside>

            <div class="p-6 sm:p-10 lg:p-12">
                <div class="mb-8 flex items-start justify-between gap-5">
                    <div>
                        <p
                            class="text-xs font-black tracking-[0.24em] text-[#c84f65] uppercase"
                        >
                            New installation
                        </p>
                        <h2
                            class="mt-2 text-4xl leading-none font-black tracking-[-0.04em] uppercase sm:text-5xl"
                        >
                            Create timeline
                        </h2>
                    </div>
                    <span
                        aria-hidden="true"
                        class="flex size-12 shrink-0 rotate-6 items-center justify-center border-3 border-zinc-950 bg-[#f8cc5d] text-xl shadow-[4px_4px_0_#18181b]"
                    >
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
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
                            class="mb-4 border-b-3 border-zinc-950 pb-2 text-sm font-black tracking-[0.18em] uppercase"
                        >
                            Your timeline
                        </legend>

                        <label class="grid gap-2 text-sm font-black uppercase">
                            Timeline name
                            <input
                                name="timeline_name"
                                type="text"
                                maxlength="100"
                                required
                                autofocus
                                placeholder="The Morgan Family"
                                class="min-h-13 border-3 border-zinc-950 bg-[#fffdf6] px-4 py-3 text-base font-semibold normal-case outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:bg-white focus:shadow-[5px_5px_0_#e06573]"
                                :aria-invalid="Boolean(errors.timeline_name)"
                            />
                            <span
                                v-if="errors.timeline_name"
                                class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                            >
                                {{ errors.timeline_name }}
                            </span>
                        </label>

                        <label class="grid gap-2 text-sm font-black uppercase">
                            Short description
                            <textarea
                                name="timeline_description"
                                rows="3"
                                maxlength="280"
                                placeholder="A place for our family stories, milestones and memories."
                                class="resize-y border-3 border-zinc-950 bg-[#fffdf6] px-4 py-3 text-base font-semibold normal-case outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:bg-white focus:shadow-[5px_5px_0_#77c8b5]"
                                :aria-invalid="
                                    Boolean(errors.timeline_description)
                                "
                            ></textarea>
                            <span
                                v-if="errors.timeline_description"
                                class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                            >
                                {{ errors.timeline_description }}
                            </span>
                        </label>
                    </fieldset>

                    <fieldset class="grid gap-5">
                        <legend
                            class="mb-4 border-b-3 border-zinc-950 pb-2 text-sm font-black tracking-[0.18em] uppercase"
                        >
                            First account
                        </legend>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <label
                                class="grid gap-2 text-sm font-black uppercase"
                            >
                                Your name
                                <input
                                    name="name"
                                    type="text"
                                    autocomplete="name"
                                    required
                                    placeholder="Alex Morgan"
                                    class="min-h-13 border-3 border-zinc-950 bg-[#fffdf6] px-4 py-3 text-base font-semibold normal-case outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:bg-white focus:shadow-[5px_5px_0_#e06573]"
                                    :aria-invalid="Boolean(errors.name)"
                                />
                                <span
                                    v-if="errors.name"
                                    class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                    >{{ errors.name }}</span
                                >
                            </label>

                            <label
                                class="grid gap-2 text-sm font-black uppercase"
                            >
                                Username
                                <input
                                    name="username"
                                    type="text"
                                    autocomplete="username"
                                    required
                                    placeholder="alex"
                                    class="min-h-13 border-3 border-zinc-950 bg-[#fffdf6] px-4 py-3 text-base font-semibold lowercase outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:bg-white focus:shadow-[5px_5px_0_#77c8b5]"
                                    :aria-invalid="Boolean(errors.username)"
                                />
                                <span
                                    v-if="errors.username"
                                    class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                    >{{ errors.username }}</span
                                >
                            </label>
                        </div>

                        <label class="grid gap-2 text-sm font-black uppercase">
                            Email address
                            <input
                                name="email"
                                type="email"
                                autocomplete="email"
                                required
                                placeholder="alex@example.com"
                                class="min-h-13 border-3 border-zinc-950 bg-[#fffdf6] px-4 py-3 text-base font-semibold normal-case outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:bg-white focus:shadow-[5px_5px_0_#f8cc5d]"
                                :aria-invalid="Boolean(errors.email)"
                            />
                            <span
                                v-if="errors.email"
                                class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                >{{ errors.email }}</span
                            >
                        </label>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <label
                                class="grid gap-2 text-sm font-black uppercase"
                            >
                                Password
                                <input
                                    name="password"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    placeholder="Create a password"
                                    class="min-h-13 border-3 border-zinc-950 bg-[#fffdf6] px-4 py-3 text-base font-semibold normal-case outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:bg-white focus:shadow-[5px_5px_0_#e06573]"
                                    :aria-invalid="Boolean(errors.password)"
                                />
                                <span
                                    v-if="errors.password"
                                    class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                    >{{ errors.password }}</span
                                >
                            </label>

                            <label
                                class="grid gap-2 text-sm font-black uppercase"
                            >
                                Confirm password
                                <input
                                    name="password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    placeholder="Repeat your password"
                                    class="min-h-13 border-3 border-zinc-950 bg-[#fffdf6] px-4 py-3 text-base font-semibold normal-case outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:bg-white focus:shadow-[5px_5px_0_#77c8b5]"
                                />
                            </label>
                        </div>
                    </fieldset>

                    <button
                        type="submit"
                        :disabled="processing"
                        class="min-h-14 cursor-pointer border-3 border-zinc-950 bg-[#e06573] px-6 py-3 text-sm font-black tracking-wider text-white uppercase shadow-[6px_6px_0_#18181b] transition hover:-translate-x-0.5 hover:-translate-y-0.5 hover:bg-[#c84f65] hover:shadow-[8px_8px_0_#18181b] focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-[#77c8b5] active:translate-x-1 active:translate-y-1 active:shadow-[2px_2px_0_#18181b] disabled:cursor-not-allowed disabled:bg-zinc-300 disabled:text-zinc-600"
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
