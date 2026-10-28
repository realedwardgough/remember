<template>
    <Modal
        :show="image !== null"
        :title="title || image?.name || 'Photo'"
        eyebrow="A closer look"
        wide
        @close="emit('close')"
    >
        <div v-if="image" class="grid gap-4">
            <img
                :src="image.url"
                :alt="image.name"
                class="mx-auto max-h-[60dvh] max-w-full rounded-xl object-contain"
            />
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p
                    class="min-w-0 flex-1 text-xs leading-5 break-words text-stone-500"
                >
                    {{ image.name
                    }}<span v-if="subtitle"> · {{ subtitle }}</span
                    ><span v-if="image.width && image.height">
                        · {{ image.width }} × {{ image.height }}</span
                    >
                </p>
                <a :href="image.downloadUrl" class="button-secondary"
                    ><Download :size="17" />Download</a
                >
            </div>
        </div>
    </Modal>
</template>
<script setup lang="ts">
import { Download } from 'lucide-vue-next';
import Modal from '@/components/Modal.vue';
import type { ImageViewerMedia } from '@/types';
defineProps<{
    image: ImageViewerMedia | null;
    title?: string | null;
    subtitle?: string | null;
}>();
const emit = defineEmits<{ close: [] }>();
</script>
