<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="image"
                class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/85 p-3 backdrop-blur-sm sm:p-6"
                role="presentation"
                @click.self="emit('close')"
            >
                <section
                    class="grid max-h-full w-full max-w-6xl border-4 border-zinc-950 bg-[#f7f1e7] shadow-[8px_8px_0_#e06573]"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="`Viewing ${image.name}`"
                >
                    <header
                        class="flex flex-col gap-4 border-b-4 border-zinc-950 bg-[#77c8b5] p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
                    >
                        <div class="min-w-0">
                            <p
                                class="inline-flex border-2 border-zinc-950 bg-[#f4ce63] px-2 py-0.5 text-[0.65rem] font-black tracking-[0.18em] text-zinc-950 uppercase"
                            >
                                Image preview
                            </p>
                            <h2
                                class="mt-2 truncate text-xl font-black text-zinc-950 sm:text-2xl"
                            >
                                {{ title ?? image.name }}
                            </h2>
                            <p
                                class="mt-1 truncate text-xs font-bold text-zinc-700 sm:text-sm"
                            >
                                <span v-if="title">{{ image.name }} · </span>
                                {{ imageDetails }}
                                <span v-if="subtitle"> · {{ subtitle }}</span>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <a
                                :href="image.downloadUrl"
                                class="inline-flex min-h-11 items-center justify-center gap-2 border-3 border-zinc-950 bg-[#f4ce63] px-4 text-sm font-black text-zinc-950 shadow-[4px_4px_0_#18181b] transition hover:-translate-y-0.5 hover:shadow-[6px_6px_0_#18181b] focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-white"
                            >
                                <i
                                    class="fa-solid fa-download"
                                    aria-hidden="true"
                                ></i>
                                Download
                            </a>
                            <button
                                ref="closeButton"
                                type="button"
                                class="inline-flex size-11 cursor-pointer items-center justify-center border-3 border-zinc-950 bg-white text-zinc-950 shadow-[4px_4px_0_#18181b] transition hover:-translate-y-0.5 hover:bg-[#e06573] hover:text-white hover:shadow-[6px_6px_0_#18181b] focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-white"
                                aria-label="Close image viewer"
                                @click="emit('close')"
                            >
                                <i
                                    class="fa-solid fa-xmark"
                                    aria-hidden="true"
                                ></i>
                            </button>
                        </div>
                    </header>

                    <div
                        class="flex min-h-0 items-center justify-center overflow-hidden bg-zinc-950 p-2 sm:p-4"
                    >
                        <img
                            :src="image.url"
                            :alt="image.name"
                            class="max-h-[72vh] w-auto max-w-full border-3 border-zinc-950 bg-white object-contain"
                        />
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import type { ImageViewerMedia } from '@/types';

const props = defineProps<{
    image: ImageViewerMedia | null;
    title?: string | null;
    subtitle?: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const closeButton = ref<HTMLButtonElement | null>(null);
let previouslyFocusedElement: HTMLElement | null = null;

const imageDetails = computed(() => {
    if (props.image === null) {
        return '';
    }

    if (props.image.width === null || props.image.height === null) {
        return props.image.mimeType;
    }

    return `${props.image.width} x ${props.image.height}`;
});

const closeOnEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

watch(
    () => props.image,
    async (image) => {
        if (typeof document === 'undefined') {
            return;
        }

        document.body.classList.toggle('overflow-hidden', image !== null);

        if (image !== null) {
            previouslyFocusedElement = document.activeElement as HTMLElement;
            document.addEventListener('keydown', closeOnEscape);
            await nextTick();
            closeButton.value?.focus();

            return;
        }

        document.removeEventListener('keydown', closeOnEscape);
        previouslyFocusedElement?.focus();
        previouslyFocusedElement = null;
    },
);

onBeforeUnmount(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.classList.remove('overflow-hidden');
    document.removeEventListener('keydown', closeOnEscape);
});
</script>
