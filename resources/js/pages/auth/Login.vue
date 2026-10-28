<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { home } from '@/routes';
import { store } from '@/routes/login';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <main
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-canvas px-5 py-10 text-zinc-950 sm:px-8 lg:px-12"
    >
        <Head title="Log in" />

        <section
            class="relative z-10 grid w-full max-w-5xl rounded-xl border border-stone-200 bg-white lg:grid-cols-[0.9fr_1.1fr]"
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
                        Your family story
                    </div>
                    <h1 class="page-heading">Pick up where you left off.</h1>
                    <p
                        class="mt-5 max-w-xs text-base leading-relaxed font-semibold"
                    >
                        Sign in to revisit the moments, people and stories that
                        matter most.
                    </p>
                </div>
            </aside>

            <div class="flex items-center bg-white p-7 sm:p-10 lg:p-14">
                <div class="w-full">
                    <div class="mb-8 flex items-start justify-between gap-5">
                        <div>
                            <p
                                class="mb-2 text-xs font-semibold tracking-normal text-primary"
                            >
                                Welcome back
                            </p>
                            <h2 class="page-heading">Log in</h2>
                        </div>
                        <span
                            aria-hidden="true"
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-stone-200 bg-mint text-2xl font-semibold"
                        >
                            →
                        </span>
                    </div>

                    <p
                        v-if="status"
                        role="status"
                        class="mb-6 rounded-xl border border-stone-200 bg-mint px-4 py-3 text-sm font-bold"
                    >
                        {{ status }}
                    </p>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="grid gap-5"
                    >
                        <label class="grid gap-2 text-sm font-semibold">
                            Email address
                            <input
                                name="email"
                                type="email"
                                autocomplete="email"
                                autofocus
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

                        <label class="grid gap-2 text-sm font-semibold">
                            Password
                            <input
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                                placeholder="Enter your password"
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

                        <label
                            class="flex w-fit cursor-pointer items-center gap-3 text-sm font-bold"
                        >
                            <input
                                name="remember"
                                type="checkbox"
                                value="1"
                                class="size-5 accent-primary focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary"
                            />
                            Keep me logged in
                        </label>

                        <button
                            type="submit"
                            :disabled="processing"
                            class="mt-1 min-h-13 cursor-pointer rounded-xl border border-stone-200 bg-primary px-5 py-3 text-sm font-semibold tracking-wider text-white transition-[background-color,box-shadow,translate] hover:bg-violet-800 focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{
                                processing
                                    ? 'Logging in…'
                                    : 'Enter the timeline'
                            }}
                        </button>
                    </Form>

                    <p
                        class="mt-8 border-t border-stone-200 pt-5 text-sm font-semibold"
                    >
                        Private by design. Your family memories stay with your
                        family.
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>
