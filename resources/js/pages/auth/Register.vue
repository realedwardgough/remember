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
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-canvas px-5 py-10 text-zinc-950 sm:px-8 lg:px-12"
    >
        <Head title="Register" />

        <section
            class="relative z-10 grid w-full max-w-6xl rounded-xl border border-stone-200 bg-white lg:grid-cols-[0.8fr_1.2fr]"
        >
            <aside
                class="relative flex flex-col justify-between gap-12 overflow-hidden border-b border-stone-200 bg-lilac p-7 sm:p-10 lg:border-r lg:border-b-0 lg:p-12"
            >
                <Link
                    :href="home()"
                    class="relative z-10 flex w-fit flex-col text-primary focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-zinc-950"
                    aria-label="Remember home"
                >
                    <span class="story-font text-3xl leading-none lg:text-5xl">
                        Remember
                    </span>
                    <span
                        class="text-sm font-semibold tracking-normal text-zinc-950"
                    >
                        Timeline
                    </span>
                </Link>

                <div class="relative z-10 hidden max-w-sm lg:block">
                    <div
                        class="mb-6 inline-flex items-center rounded-xl border border-stone-200 bg-butter px-3 py-1.5 text-xs font-semibold tracking-widest"
                    >
                        By invitation
                    </div>
                    <h1 class="page-heading">Add your voice to the story.</h1>
                    <p
                        class="mt-5 max-w-xs text-base leading-relaxed font-semibold"
                    >
                        Create your account and help preserve the moments your
                        family never wants to forget.
                    </p>
                </div>
            </aside>

            <div class="flex items-center bg-white p-7 sm:p-10 lg:p-12">
                <div class="w-full">
                    <div class="mb-7 flex items-start justify-between gap-5">
                        <div>
                            <p
                                class="mb-2 text-xs font-semibold tracking-normal text-primary"
                            >
                                {{
                                    invite
                                        ? 'Invitation accepted'
                                        : 'Invite required'
                                }}
                            </p>
                            <h2 class="page-heading">Register</h2>
                        </div>
                        <span
                            aria-hidden="true"
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-stone-200 bg-mint text-2xl font-semibold"
                        >
                            +
                        </span>
                    </div>

                    <div
                        v-if="invite"
                        class="mb-6 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-stone-200 bg-mint px-4 py-3"
                    >
                        <span class="text-xs font-semibold tracking-widest">
                            Your username
                        </span>
                        <span class="text-base font-semibold">
                            @{{ invite.username }}
                        </span>
                    </div>
                    <div
                        v-else
                        role="status"
                        class="mb-6 rounded-xl border border-stone-200 bg-butter px-4 py-3"
                    >
                        <p class="text-sm font-semibold">
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
                            class="border-l border-red-600 pl-2 text-sm font-bold text-red-700"
                        >
                            {{ errors.invitation_token }}
                        </span>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="grid gap-2 text-sm font-semibold">
                                Name
                                <input
                                    name="name"
                                    type="text"
                                    autocomplete="name"
                                    autofocus
                                    required
                                    placeholder="Your name"
                                    class="min-h-13 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case transition-[background-color,box-shadow,translate] placeholder:font-medium placeholder:text-zinc-400 focus:bg-white"
                                    :aria-invalid="Boolean(errors.name)"
                                    :aria-describedby="
                                        errors.name ? 'name-error' : undefined
                                    "
                                />
                                <span
                                    v-if="errors.name"
                                    id="name-error"
                                    class="border-l border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                >
                                    {{ errors.name }}
                                </span>
                            </label>

                            <label class="grid gap-2 text-sm font-semibold">
                                Email address
                                <input
                                    name="email"
                                    type="email"
                                    autocomplete="email"
                                    required
                                    placeholder="you@example.com"
                                    class="min-h-13 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case transition-[background-color,box-shadow,translate] placeholder:font-medium placeholder:text-zinc-400 focus:bg-white"
                                    :aria-invalid="Boolean(errors.email)"
                                    :aria-describedby="
                                        errors.email ? 'email-error' : undefined
                                    "
                                />
                                <span
                                    v-if="errors.email"
                                    id="email-error"
                                    class="border-l border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                >
                                    {{ errors.email }}
                                </span>
                            </label>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="grid gap-2 text-sm font-semibold">
                                Password
                                <input
                                    name="password"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    placeholder="Create a password"
                                    class="min-h-13 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case transition-[background-color,box-shadow,translate] placeholder:font-medium placeholder:text-zinc-400 focus:bg-white"
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
                                    class="border-l border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                >
                                    {{ errors.password }}
                                </span>
                            </label>

                            <label class="grid gap-2 text-sm font-semibold">
                                Confirm password
                                <input
                                    name="password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    placeholder="Repeat your password"
                                    class="min-h-13 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case transition-[background-color,box-shadow,translate] placeholder:font-medium placeholder:text-zinc-400 focus:bg-white"
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
                                    class="border-l border-red-600 pl-2 text-sm font-bold text-red-700 normal-case"
                                >
                                    {{ errors.password_confirmation }}
                                </span>
                            </label>
                        </div>

                        <button
                            type="submit"
                            :disabled="processing || !invite"
                            class="mt-1 min-h-13 cursor-pointer rounded-xl border border-stone-200 bg-primary px-5 py-3 text-sm font-semibold tracking-wider text-white transition-[background-color,box-shadow,translate] hover:bg-violet-800 focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-primary disabled:cursor-not-allowed disabled:bg-zinc-300 disabled:text-zinc-600 disabled:hover:translate-0"
                        >
                            {{
                                processing
                                    ? 'Creating account…'
                                    : 'Join the timeline'
                            }}
                        </button>
                    </Form>

                    <p
                        class="mt-7 border-t border-stone-200 pt-5 text-sm font-semibold"
                    >
                        Already part of the story?
                        <Link
                            :href="login()"
                            class="font-semibold text-primary underline decoration-2 underline-offset-4 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        >
                            Log in
                        </Link>
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>
