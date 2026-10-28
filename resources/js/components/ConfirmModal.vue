<template>
    <Modal
        :show="show"
        :busy="processing"
        :title="title"
        :eyebrow="eyebrow"
        @close="emit('close')"
    >
        <div class="grid gap-6">
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <p class="text-base leading-6 font-bold text-zinc-800">
                    {{ message }}
                </p>
                <slot />
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    :disabled="processing"
                    class="min-h-11 cursor-pointer rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-950 transition hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-60"
                    @click="emit('close')"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    class="min-h-11 cursor-pointer rounded-xl border border-stone-200 bg-primary px-4 py-2.5 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60"
                    @click="emit('confirm')"
                >
                    <AppIcon
                        name="fa-solid fa-triangle-exclamation"
                        class="mr-2"
                    />
                    {{ processing ? processingLabel : confirmLabel }}
                </button>
            </div>
        </div>
    </Modal>
</template>

<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue';
import Modal from '@/components/Modal.vue';

withDefaults(
    defineProps<{
        show: boolean;
        title: string;
        message: string;
        eyebrow?: string;
        confirmLabel?: string;
        cancelLabel?: string;
        processing?: boolean;
        processingLabel?: string;
    }>(),
    {
        eyebrow: 'Please confirm',
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        processing: false,
        processingLabel: 'Working…',
    },
);

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();
</script>
