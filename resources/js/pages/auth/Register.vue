<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { home, login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    invite?: {
        token: string;
        username: string;
    } | null;
}>();
</script>

<template>
    <main
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#fff9e8] px-5 py-10 text-zinc-950 sm:px-8 lg:px-12"
    >
        <Head title="Register" />

        <div
            aria-hidden="true"
            class="absolute -top-10 -left-12 size-32 rotate-12 border-4 border-zinc-950 bg-[#77c8b5] shadow-secondary sm:size-44"
        ></div>
        <div
            aria-hidden="true"
            class="absolute top-20 -right-10 size-24 rounded-full border-4 border-zinc-950 bg-[#f8cc5d] shadow-secondary sm:right-8 sm:size-32"
        ></div>
        <div
            aria-hidden="true"
            class="absolute -right-8 -bottom-12 h-36 w-64 -rotate-6 border-4 border-zinc-950 bg-[#e06573] shadow-secondary sm:h-44 sm:w-80"
        ></div>

        <section
            class="relative z-10 grid w-full max-w-6xl border-4 border-zinc-950 bg-white shadow-[10px_10px_0_0_#18181b] lg:grid-cols-[0.8fr_1.2fr]"
        >
            <aside
                class="relative flex flex-col justify-between gap-12 overflow-hidden border-b-4 border-zinc-950 bg-[#e06573] p-7 sm:p-10 lg:border-r-4 lg:border-b-0 lg:p-12"
            >
                <Link
                    :href="home()"
                    class="relative z-10 flex w-fit flex-col text-white focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-zinc-950"
                    aria-label="Remember home"
                >
                    <span
                        class="story-font text-6xl leading-none font-bold sm:text-7xl"
                    >
                        Remember
                    </span>
                    <span
                        class="text-sm font-black tracking-[0.28em] text-zinc-950 uppercase"
                    >
                        Timeline
                    </span>
                </Link>

                <div class="relative z-10 max-w-sm">
                    <div
                        class="mb-6 inline-flex -rotate-2 items-center border-3 border-zinc-950 bg-[#f8cc5d] px-3 py-1.5 text-xs font-black tracking-widest uppercase shadow-[4px_4px_0_0_#18181b]"
                    >
                        By invitation
                    </div>
                    <h1
                        class="text-4xl leading-[0.95] font-black tracking-[-0.04em] text-white uppercase sm:text-5xl"
                    >
                        Add your voice to the story.
                    </h1>
                    <p
                        class="mt-5 max-w-xs text-base leading-relaxed font-semibold"
                    >
                        Create your account and help preserve the moments your
                        family never wants to forget.
                    </p>
                </div>

                <div
                    aria-hidden="true"
                    class="absolute -right-12 -bottom-14 size-40 -rotate-12 border-4 border-zinc-950 bg-[#77c8b5] lg:size-52"
                ></div>
            </aside>

            <div class="flex items-center bg-white p-7 sm:p-10 lg:p-12">
                <div class="w-full">
                    <div class="mb-7 flex items-start justify-between gap-5">
                        <div>
                            <p
                                class="mb-2 text-xs font-black tracking-[0.24em] text-[#c84f65] uppercase"
                            >
                                {{
                                    invite
                                        ? 'Invitation accepted'
                                        : 'Invite required'
                                }}
                            </p>
                            <h2
                                class="text-4xl leading-none font-black tracking-[-0.04em] uppercase sm:text-5xl"
                            >
                                Register
                            </h2>
                        </div>
                        <span
                            aria-hidden="true"
                            class="flex size-12 shrink-0 rotate-6 items-center justify-center border-3 border-zinc-950 bg-[#77c8b5] text-2xl font-black shadow-[4px_4px_0_0_#18181b]"
                        >
                            +
                        </span>
                    </div>

                    <div
                        v-if="invite"
                        class="mb-6 flex flex-wrap items-center justify-between gap-2 border-3 border-zinc-950 bg-[#b9eadf] px-4 py-3 shadow-[4px_4px_0_0_#18181b]"
                    >
                        <span
                            class="text-xs font-black tracking-widest uppercase"
                        >
                            Your username
                        </span>
                        <span class="text-base font-black">
                            @{{ invite.username }}
                        </span>
                    </div>
                    <div
                        v-else
                        role="status"
                        class="mb-6 border-3 border-zinc-950 bg-[#fff0b8] px-4 py-3 shadow-[4px_4px_0_0_#18181b]"
                    >
                        <p class="text-sm font-black uppercase">
                            Registration is invite only
                        </p>
                        <p class="mt-1 text-sm font-semibold">
                            Open the personal invitation link sent to you to
                            create an account.
                        </p>
                    </div>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="[
                            'password',
                            'password_confirmation',
                        ]"
                        v-slot="{ errors, processing }"
                        class="grid gap-5"
                    >
                        <input
                            v-if="invite"
                            type="hidden"
                            name="invitation_token"
                            :value="invite.token"
                        />
                        <span
                            v-if="errors.invitation_token"
                            id="invitation-error"
                            class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700"
                        >
                            {{ errors.invitation_token }}
                        </span>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <label
                                class="grid gap-2 text-sm font-black uppercase"
                            >
                                Name
                                <input
                                    name="name"
                                    type="text"
                                    autocomplete="name"
                                    autofocus
                                    required
                                    placeholder="Your name"
                                    class="min-h-13 border-3 border-zinc-950 bg-[#fffdf6] px-4 py-3 text-base font-semibold normal-case transition-[background-color,box-shadow,translate] outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:bg-white focus:shadow-[5px_5px_0_0_#e06573]"
                                    :aria-invalid="Boolean(errors.name)"
                                    :aria-describedby="
                                        errors.name ? 'name-error' : undefined
                                    "
                                />
                                <span
                                    v-if="errors.name"
                                    id="name-error"
                                    class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                >
                                    {{ errors.name }}
                                </span>
                            </label>

                            <label
                                class="grid gap-2 text-sm font-black uppercase"
                            >
                                Email address
                                <input
                                    name="email"
                                    type="email"
                                    autocomplete="email"
                                    required
                                    placeholder="you@example.com"
                                    class="min-h-13 border-3 border-zinc-950 bg-[#fffdf6] px-4 py-3 text-base font-semibold normal-case transition-[background-color,box-shadow,translate] outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:bg-white focus:shadow-[5px_5px_0_0_#e06573]"
                                    :aria-invalid="Boolean(errors.email)"
                                    :aria-describedby="
                                        errors.email ? 'email-error' : undefined
                                    "
                                />
                                <span
                                    v-if="errors.email"
                                    id="email-error"
                                    class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                >
                                    {{ errors.email }}
                                </span>
                            </label>
                        </div>

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
                                    class="min-h-13 border-3 border-zinc-950 bg-[#fffdf6] px-4 py-3 text-base font-semibold normal-case transition-[background-color,box-shadow,translate] outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:bg-white focus:shadow-[5px_5px_0_0_#e06573]"
                                    :aria-invalid="Boolean(errors.password)"
                                    :aria-describedby="
                                        errors.password
                                            ? 'password-error'
                                            : undefined
                                    "
                                />
                                <span
                                    v-if="errors.password"
                                    id="password-error"
                                    class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                >
                                    {{ errors.password }}
                                </span>
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
                                    class="min-h-13 border-3 border-zinc-950 bg-[#fffdf6] px-4 py-3 text-base font-semibold normal-case transition-[background-color,box-shadow,translate] outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:bg-white focus:shadow-[5px_5px_0_0_#e06573]"
                                    :aria-invalid="
                                        Boolean(errors.password_confirmation)
                                    "
                                    :aria-describedby="
                                        errors.password_confirmation
                                            ? 'password-confirmation-error'
                                            : undefined
                                    "
                                />
                                <span
                                    v-if="errors.password_confirmation"
                                    id="password-confirmation-error"
                                    class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                >
                                    {{ errors.password_confirmation }}
                                </span>
                            </label>
                        </div>

                        <button
                            type="submit"
                            :disabled="processing || !invite"
                            class="mt-1 min-h-13 cursor-pointer border-3 border-zinc-950 bg-[#e06573] px-5 py-3 text-sm font-black tracking-wider text-white uppercase shadow-[6px_6px_0_0_#18181b] transition-[background-color,box-shadow,translate] hover:-translate-x-0.5 hover:-translate-y-0.5 hover:bg-[#c84f65] hover:shadow-[8px_8px_0_0_#18181b] focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-[#77c8b5] active:translate-x-1 active:translate-y-1 active:shadow-[2px_2px_0_0_#18181b] disabled:cursor-not-allowed disabled:bg-zinc-300 disabled:text-zinc-600 disabled:shadow-[4px_4px_0_0_#18181b] disabled:hover:translate-0"
                        >
                            {{
                                processing
                                    ? 'Creating account…'
                                    : 'Join the timeline'
                            }}
                        </button>
                    </Form>

                    <p
                        class="mt-7 border-t-3 border-zinc-950 pt-5 text-sm font-semibold"
                    >
                        Already part of the story?
                        <Link
                            :href="login()"
                            class="font-black text-[#c84f65] underline decoration-2 underline-offset-4 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#77c8b5]"
                        >
                            Log in
                        </Link>
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>
