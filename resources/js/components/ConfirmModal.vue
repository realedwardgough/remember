<template>
    <Modal
        :show="show"
        :title="title"
        :eyebrow="eyebrow"
        @close="emit('close')"
    >
        <div class="grid gap-6">
            <div
                class="border-3 border-zinc-950 bg-white p-4 shadow-[4px_4px_0_#f4ce63]"
            >
                <p class="text-base leading-6 font-bold text-zinc-800">
                    {{ message }}
                </p>
                <slot />
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    :disabled="processing"
                    class="min-h-11 cursor-pointer border-3 border-zinc-950 bg-white px-4 py-2.5 text-sm font-black text-zinc-950 shadow-[3px_3px_0_#18181b] transition hover:-translate-y-0.5 hover:bg-[#f7f1e7] disabled:cursor-not-allowed disabled:opacity-60"
                    @click="emit('close')"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    class="min-h-11 cursor-pointer border-3 border-zinc-950 bg-[#e06573] px-4 py-2.5 text-sm font-black text-white shadow-[4px_4px_0_#18181b] transition hover:-translate-y-0.5 hover:shadow-[6px_6px_0_#18181b] disabled:cursor-not-allowed disabled:opacity-60"
                    @click="emit('confirm')"
                >
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                    {{ processing ? processingLabel : confirmLabel }}
                </button>
            </div>
        </div>
    </Modal>
</template>

<script setup lang="ts">
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
