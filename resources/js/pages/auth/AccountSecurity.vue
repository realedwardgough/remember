<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';

defineProps<{
    enabled: boolean;
    pending: boolean;
    qrCode: string | null;
    setupKey: string | null;
    recoveryCodes: string[];
    status?: string;
}>();
</script>

<template>
    <main class="min-h-screen bg-canvas px-5 py-10 text-zinc-950">
        <Head title="Account security" />
        <section class="surface mx-auto grid max-w-xl gap-5 p-6 sm:p-8">
            <h1 class="text-2xl font-semibold">Two-factor authentication</h1>
            <p v-if="status" role="status" class="rounded-xl bg-mint p-3">
                {{ status }}
            </p>
            <p>
                Use an authenticator app to add a second check when you log in.
                Save your recovery codes before leaving this page.
            </p>
            <Form
                v-if="!enabled && !pending"
                action="/profile/security/enable"
                method="post"
                v-slot="{ processing }"
            >
                <button class="button-primary" :disabled="processing">
                    Set up authenticator app
                </button>
            </Form>
            <template v-if="pending">
                <p>
                    Scan this QR code with your authenticator app, or enter the
                    setup key manually.
                </p>
                <img
                    v-if="qrCode"
                    :src="qrCode"
                    alt="Authenticator app setup QR code"
                    width="192"
                    height="192"
                    class="rounded-xl bg-white p-3"
                />
                <code class="rounded-lg bg-white p-3 break-all">{{
                    setupKey
                }}</code>
                <Form
                    action="/profile/security/confirm"
                    error-bag="confirmTwoFactorAuthentication"
                    method="post"
                    reset-on-success
                    v-slot="{ errors, processing }"
                    class="grid gap-3"
                >
                    <label class="grid gap-2"
                        >Six-digit code<input
                            name="code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            pattern="[0-9]{6}"
                            required
                            class="rounded-xl border p-3"
                    /></label>
                    <p v-if="errors.code" role="alert" class="text-red-700">
                        {{ errors.code }}
                    </p>
                    <button
                        class="button-primary justify-self-start"
                        :disabled="processing"
                    >
                        Confirm and enable
                    </button>
                </Form>
            </template>
            <template v-if="enabled">
                <p class="font-semibold">
                    Two-factor authentication is enabled.
                </p>
            </template>
            <section
                v-if="recoveryCodes.length"
                class="grid gap-3 border-t pt-4"
            >
                <h2 class="font-semibold">Recovery codes</h2>
                <p class="text-sm">
                    Keep these somewhere private. Each code can be used once if
                    you lose access to your authenticator app.
                </p>
                <ul
                    class="grid grid-cols-1 gap-2 rounded-xl bg-white p-4 font-mono text-sm sm:grid-cols-2"
                >
                    <li v-for="code in recoveryCodes" :key="code">
                        {{ code }}
                    </li>
                </ul>
                <Form
                    v-if="enabled"
                    action="/profile/security/recovery-codes"
                    method="post"
                    v-slot="{ processing }"
                    ><button class="button-secondary" :disabled="processing">
                        Generate new recovery codes
                    </button></Form
                >
            </section>
            <Form
                v-if="enabled || pending"
                action="/profile/security"
                method="delete"
                v-slot="{ processing }"
                ><button class="button-secondary" :disabled="processing">
                    {{
                        pending
                            ? 'Cancel setup'
                            : 'Disable two-factor authentication'
                    }}
                </button></Form
            >
            <Link href="/profile" class="text-primary underline"
                >Back to profile</Link
            >
        </section>
    </main>
</template>
