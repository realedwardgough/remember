<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed top-4 right-4 z-[100] grid w-[min(24rem,calc(100vw-2rem))] gap-3"
            aria-live="polite"
            aria-atomic="false"
        >
            <TransitionGroup
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-x-6 opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-6 opacity-0"
                move-class="transition duration-200"
            >
                <button
                    v-for="toast in toasts"
                    :key="toast.id"
                    type="button"
                    class="pointer-events-auto flex w-full cursor-pointer items-start gap-3 border-3 border-zinc-950 bg-[#77c8b5] p-4 text-left text-zinc-950 shadow-[6px_6px_0_#18181b] transition hover:-translate-y-0.5"
                    :aria-label="`${toast.message}. Click to dismiss.`"
                    @click="dismiss(toast.id)"
                >
                    <i
                        class="fa-solid fa-circle-check mt-0.5"
                        aria-hidden="true"
                    ></i>
                    <span class="min-w-0 flex-1 text-sm font-black">
                        {{ toast.message }}
                    </span>
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

type Toast = {
    id: number;
    message: string;
};

const duration = 5000;
const toasts = ref<Toast[]>([]);
const timers = new Map<number, ReturnType<typeof setTimeout>>();
let nextId = 1;

function show(message: string): void {
    const id = nextId++;

    toasts.value.push({ id, message });
    timers.set(
        id,
        setTimeout(() => dismiss(id), duration),
    );
}

function dismiss(id: number): void {
    const timer = timers.get(id);

    if (timer) {
        clearTimeout(timer);
        timers.delete(id);
    }

    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

const removeFlashListener = router.on('flash', (event) => {
    const toast = event.detail.flash.toast;

    if (toast) {
        show(toast.message);
    }
});

onBeforeUnmount(() => {
    removeFlashListener();
    timers.forEach((timer) => clearTimeout(timer));
    timers.clear();
});
</script>
