<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{ action: string; error?: string }>();
const page = usePage<{
    turnstile: { enabled: boolean; siteKey: string | null };
}>();
const container = ref<HTMLDivElement | null>(null);
const unavailable = ref(false);
type Turnstile = {
    render: (element: HTMLElement, options: Record<string, unknown>) => string;
    reset: (id: string) => void;
    remove: (id: string) => void;
};
const api = () => (window as Window & { turnstile?: Turnstile }).turnstile;
let widget: string | undefined;
let disposed = false;
const render = () => {
    if (disposed || !container.value || widget !== undefined) {
        return;
    }

    widget = api()?.render(container.value, {
        sitekey: page.props.turnstile.siteKey,
        action: props.action,
        theme: 'auto',
        'error-callback': () => {
            unavailable.value = true;
        },
        callback: () => {
            unavailable.value = false;
        },
        'expired-callback': () => {
            if (widget !== undefined) {
                api()?.reset(widget);
            }
        },
    });
};
const reset = () => {
    if (widget !== undefined) {
        api()?.reset(widget);
    }
};
defineExpose({ reset });
onMounted(() => {
    if (!page.props.turnstile.enabled) {
        return;
    }

    if (!page.props.turnstile.siteKey) {
        unavailable.value = true;

        return;
    }

    if (api()) {
        render();

        return;
    }

    const existing = document.querySelector<HTMLScriptElement>(
        'script[data-turnstile]',
    );
    const script = existing ?? document.createElement('script');
    script.addEventListener('load', render, { once: true });
    script.addEventListener(
        'error',
        () => {
            unavailable.value = true;
        },
        { once: true },
    );

    if (!existing) {
        script.src =
            'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.dataset.turnstile = 'true';
        document.head.append(script);
    }
});
onBeforeUnmount(() => {
    disposed = true;

    if (widget !== undefined) {
        api()?.remove(widget);
    }
});
</script>

<template>
    <div v-if="page.props.turnstile.enabled" class="grid gap-2">
        <div ref="container" />
        <p
            v-if="error || unavailable"
            role="alert"
            class="text-sm text-red-700"
        >
            {{
                error ||
                'The security check is unavailable. Please reload this page and try again.'
            }}
        </p>
    </div>
</template>
