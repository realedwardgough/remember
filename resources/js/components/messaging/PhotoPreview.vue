<template>
    <div
        class="relative size-20 shrink-0 overflow-hidden rounded-xl border bg-lilac"
    >
        <img
            v-if="!failed"
            :src="url"
            :alt="file.name"
            class="size-full object-cover"
            @error="failed = true"
        />
        <div
            v-else
            class="flex size-full items-center justify-center p-2 text-center text-xs text-stone-500"
        >
            Photo selected
        </div>
        <button
            v-if="removable"
            type="button"
            class="absolute top-0 right-0 flex size-11 items-center justify-center rounded-full bg-white text-stone-700 shadow"
            :aria-label="`Remove ${file.name}`"
            @click="emit('remove')"
        >
            <X :size="15" />
        </button>
    </div>
</template>
<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
const props = defineProps<{ file: File; removable?: boolean }>();
const emit = defineEmits<{ remove: [] }>();
const url = ref('');
const failed = ref(false);
watch(
    () => props.file,
    (file) => {
        failed.value = false;

        if (url.value) {
            URL.revokeObjectURL(url.value);
        }

        url.value = URL.createObjectURL(file);
    },
    { immediate: true },
);
onBeforeUnmount(() => URL.revokeObjectURL(url.value));
</script>
