<template>
    <div
        class="mt-2 max-w-72 overflow-hidden rounded-2xl border bg-white text-stone-700"
    >
        <template v-if="available && !failed">
            <button
                type="button"
                class="block w-full cursor-zoom-in"
                aria-label="Open shared photo"
                @click="open = true"
            >
                <img
                    :src="attachment.thumbnail_url!"
                    alt="Shared photo"
                    :width="attachment.width"
                    :height="attachment.height"
                    class="max-h-72 w-full object-contain"
                    @error="failed = true"
                    @load="emit('loaded')"
                />
            </button>
            <p class="px-3 py-2 text-[11px] text-stone-500">
                Available until {{ expiryLabel }}
            </p>
        </template>
        <div
            v-else
            class="flex items-start gap-3 p-4 text-sm leading-6"
            role="status"
        >
            <ImageOff :size="22" class="mt-1 shrink-0 text-stone-400" />
            <div>
                <p>{{ placeholder }}</p>
                <button
                    v-if="available && failed"
                    type="button"
                    class="mt-2 min-h-11 text-primary underline"
                    @click="retry"
                >
                    Try loading photo again
                </button>
            </div>
        </div>
        <Modal
            :show="open && available"
            title="Shared photo"
            wide
            @close="open = false"
            ><img
                v-if="open && available"
                :src="attachment.url!"
                alt="Shared photo"
                class="mx-auto max-h-[65dvh] max-w-full rounded-xl object-contain"
            />
            <p class="mt-3 text-xs text-stone-500">
                Available until {{ expiryLabel }}
            </p></Modal
        >
    </div>
</template>
<script setup lang="ts">
import { ImageOff } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Modal from '@/components/Modal.vue';
import type { MessageAttachment } from '@/types/messaging';
const props = defineProps<{ attachment: MessageAttachment; now: number }>();
const emit = defineEmits<{ check: []; loaded: [] }>();
const open = ref(false);
const failed = ref(false);
let lastCheck = 0;
const expired = computed(
    () => Date.parse(props.attachment.expires_at) <= props.now,
);
const available = computed(
    () => props.attachment.status === 'available' && !expired.value,
);
const expiryLabel = computed(() =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(props.attachment.expires_at)),
);
const placeholder = computed(() => {
    if (props.attachment.status === 'removed') {
        return props.attachment.placeholder;
    }

    if (!available.value) {
        const hours = props.attachment.retention_hours;

        return `This image is older than ${hours} ${hours === 1 ? 'hour' : 'hours'} and is no longer available.`;
    }

    return 'This photo couldn’t be loaded. Check your connection and try again.';
});
function retry() {
    failed.value = false;
    emit('check');
}
watch(
    () => props.now,
    () => {
        if (expired.value) {
            open.value = false;
        }

        if (
            expired.value &&
            props.attachment.status !== 'removed' &&
            props.now - lastCheck >= 30000
        ) {
            lastCheck = props.now;
            emit('check');
        }
    },
    { immediate: true },
);
watch(
    () => props.attachment.url,
    () => {
        failed.value = false;
    },
);
</script>
