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
                v-if="show"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-zinc-950/70 px-4 py-6 backdrop-blur-[2px] sm:py-10"
                role="presentation"
                @click.self="emit('close')"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="translate-y-3 scale-95 opacity-0"
                    enter-to-class="translate-y-0 scale-100 opacity-100"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="translate-y-0 scale-100 opacity-100"
                    leave-to-class="translate-y-3 scale-95 opacity-0"
                >
                    <section
                        v-if="show"
                        class="w-full max-w-2xl border-4 border-zinc-950 bg-[#fff9e8] shadow-[10px_10px_0_0_#e06573]"
                        role="dialog"
                        aria-modal="true"
                        :aria-labelledby="titleId"
                    >
                        <header
                            class="relative flex items-start justify-between gap-4 overflow-hidden border-b-4 border-zinc-950 bg-[#e06573] p-5 sm:p-6"
                        >
                            <div class="relative z-10">
                                <p
                                    class="w-fit -rotate-1 border-2 border-zinc-950 bg-[#f8cc5d] px-2 py-1 text-xs font-black tracking-[0.2em] text-zinc-950 uppercase shadow-[3px_3px_0_0_#18181b]"
                                >
                                    {{ eyebrow }}
                                </p>
                                <h2
                                    :id="titleId"
                                    class="mt-4 text-3xl leading-none font-black tracking-[-0.03em] text-white uppercase sm:text-4xl"
                                >
                                    {{ title }}
                                </h2>
                            </div>

                            <button
                                type="button"
                                class="relative z-10 inline-flex size-10 shrink-0 cursor-pointer items-center justify-center border-3 border-zinc-950 bg-white text-zinc-950 shadow-[3px_3px_0_0_#18181b] hover:-translate-x-0.5 hover:-translate-y-0.5 hover:bg-[#77c8b5] focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-[#f8cc5d]"
                                aria-label="Close modal"
                                @click="emit('close')"
                            >
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                            <div
                                aria-hidden="true"
                                class="absolute -right-8 -bottom-14 size-32 rotate-12 border-4 border-zinc-950 bg-[#77c8b5]"
                            ></div>
                        </header>

                        <div class="p-5 sm:p-6">
                            <slot />
                        </div>
                    </section>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from 'vue';

const props = defineProps<{
    show: boolean;
    title: string;
    eyebrow?: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const titleId = computed(() => {
    return `modal-${props.title.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
});

const closeOnEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

watch(
    () => props.show,
    (isShown) => {
        if (typeof document === 'undefined') {
            return;
        }

        document.body.classList.toggle('overflow-hidden', isShown);

        if (isShown) {
            document.addEventListener('keydown', closeOnEscape);
        } else {
            document.removeEventListener('keydown', closeOnEscape);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.classList.remove('overflow-hidden');
    document.removeEventListener('keydown', closeOnEscape);
});
</script>
