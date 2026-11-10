<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
const recovery = ref(false);
</script>

<template>
    <main
        class="flex min-h-screen items-center justify-center bg-canvas p-5 text-zinc-950"
    >
        <Head title="Verify your login" />
        <section class="surface grid w-full max-w-sm gap-5 p-6">
            <h1 class="text-2xl font-semibold">Verify your login</h1>
            <p>
                {{
                    recovery
                        ? 'Enter one of your saved recovery codes.'
                        : 'Enter the six-digit code from your authenticator app.'
                }}
            </p>
            <Form
                action="/two-factor-challenge"
                method="post"
                :reset-on-error="['code', 'recovery_code']"
                v-slot="{ errors, processing }"
                class="grid gap-4"
            >
                <label v-if="recovery" class="grid gap-2"
                    >Recovery code<input
                        name="recovery_code"
                        required
                        autocomplete="off"
                        class="rounded-xl border p-3"
                /></label>
                <label v-else class="grid gap-2"
                    >Authentication code<input
                        name="code"
                        required
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9]{6}"
                        class="rounded-xl border p-3"
                /></label>
                <p
                    v-if="errors.code || errors.recovery_code"
                    role="alert"
                    class="text-sm text-red-700"
                >
                    {{ errors.code || errors.recovery_code }}
                </p>
                <button class="button-primary" :disabled="processing">
                    Verify and log in
                </button>
            </Form>
            <button
                type="button"
                class="text-primary underline"
                @click="recovery = !recovery"
            >
                {{
                    recovery
                        ? 'Use an authenticator code'
                        : 'Use a recovery code'
                }}
            </button>
            <Link href="/login" class="text-sm underline">Back to login</Link>
        </section>
    </main>
</template>
