<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { home } from '@/routes';
import { store } from '@/routes/password/confirm';
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-slate-50 px-6 py-12 text-slate-950">
        <Head title="Confirm password" />

        <section class="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-semibold tracking-normal">Confirm password</h1>
            <p class="mt-2 text-sm text-slate-600">Enter your password to continue.</p>

            <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }" class="mt-6 grid gap-4">
                <label class="grid gap-2 text-sm font-medium">
                    Password
                    <input
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        autofocus
                        class="rounded-md border border-slate-300 px-3 py-2 text-base font-normal outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    />
                    <span v-if="errors.password" class="text-sm font-normal text-red-600">{{ errors.password }}</span>
                </label>

                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-md bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ processing ? 'Confirming...' : 'Confirm' }}
                </button>
            </Form>

            <Link :href="home()" class="mt-6 block text-center text-sm font-medium text-slate-600 hover:text-slate-950">
                Back to timeline
            </Link>
        </section>
    </main>
</template>
