<template>
    <form class="border-t bg-white p-3 sm:p-4" @submit.prevent="submit">
        <div v-if="photos.length" class="mb-3 flex gap-2 overflow-x-auto">
            <PhotoPreview
                v-for="(file, index) in photos"
                :key="index"
                :file="file"
                removable
                @remove="removePhoto(index)"
            />
        </div>
        <label for="message-body" class="sr-only">Message</label>
        <div class="flex items-end gap-2 rounded-2xl border bg-canvas p-2">
            <input
                ref="photoInput"
                type="file"
                class="sr-only"
                tabindex="-1"
                accept="image/jpeg,image/png,image/webp,image/gif,image/avif,image/heic,image/heif,.heic,.heif"
                multiple
                aria-label="Choose message photos"
                :disabled="disabled || !imagesEnabled"
                @change="choosePhotos"
            />
            <button
                v-if="imagesEnabled"
                type="button"
                class="flex size-11 shrink-0 items-center justify-center rounded-xl text-stone-500 hover:bg-lilac"
                :disabled="disabled"
                aria-label="Add photos"
                @click="photoInput?.click()"
            >
                <ImagePlus :size="21" />
            </button>
            <EmojiPicker
                :disabled="disabled"
                @select="insertEmoji"
                @close="input?.focus()"
            />
            <textarea
                id="message-body"
                ref="input"
                :value="modelValue"
                :disabled="disabled"
                rows="2"
                maxlength="20000"
                placeholder="Write a message…"
                class="max-h-40 min-h-12 min-w-0 flex-1 resize-y border-0 bg-transparent py-2.5 text-base outline-none"
                :aria-invalid="tooLong || undefined"
                :aria-describedby="tooLong ? 'message-length-error' : undefined"
                @input="update"
                @blur="emit('blur')"
                @keydown="keydown"
            />
            <button
                type="submit"
                :disabled="
                    disabled ||
                    (!modelValue.trim() && !photos.length) ||
                    tooLong
                "
                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-white"
                aria-label="Send message"
            >
                <Send :size="19" />
            </button>
        </div>
        <p v-if="photoError" role="alert" class="mt-2 text-sm text-red-700">
            {{ photoError }}
        </p>
        <p
            v-if="photos.length && retentionHours"
            class="mt-2 text-xs text-stone-500"
        >
            Photos are removed {{ retentionHours }}
            {{ retentionHours === 1 ? 'hour' : 'hours' }} after upload. Up to 4
            photos, 10 MB each.
        </p>
        <p
            v-if="tooLong"
            id="message-length-error"
            class="mt-2 text-sm text-red-700"
            role="alert"
        >
            Messages can contain up to 10,000 characters.
        </p>
        <p v-else class="mt-2 text-xs text-stone-500">
            A little note, just between you two.
        </p>
    </form>
</template>
<script setup lang="ts">
import { ImagePlus, Send } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import EmojiPicker from '@/components/messaging/EmojiPicker.vue';
import PhotoPreview from '@/components/messaging/PhotoPreview.vue';
const props = defineProps<{
    modelValue: string;
    disabled?: boolean;
    photos: File[];
    imagesEnabled: boolean;
    retentionHours: number | null;
}>();
const emit = defineEmits<{
    'update:modelValue': [value: string];
    send: [];
    blur: [];
    'update:photos': [photos: File[]];
}>();
const input = ref<HTMLTextAreaElement | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);
const photoError = ref('');
function removePhoto(index: number) {
    emit(
        'update:photos',
        props.photos.filter((_, position) => position !== index),
    );
    photoError.value = '';
}
function choosePhotos(event: Event) {
    const target = event.target as HTMLInputElement;
    const selected = [...(target.files ?? [])];
    target.value = '';

    if (props.photos.length + selected.length > 4) {
        photoError.value = 'Choose up to four photos per message.';

        return;
    }

    if (selected.some((file) => file.size > 10 * 1024 * 1024)) {
        photoError.value = 'Each photo must be 10 MB or smaller.';

        return;
    }

    photoError.value = '';
    emit('update:photos', [...props.photos, ...selected]);
}
const tooLong = computed(() => [...props.modelValue].length > 10000);
function update(event: Event) {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
}
function submit() {
    if (
        props.disabled ||
        (!props.modelValue.trim() && !props.photos.length) ||
        tooLong.value
    ) {
        return;
    }

    emit('send');
}
function keydown(event: KeyboardEvent) {
    if (
        event.key === 'Enter' &&
        (event.metaKey || event.ctrlKey) &&
        !event.isComposing
    ) {
        event.preventDefault();
        submit();
    }
}
async function insertEmoji(emoji: string) {
    const start = input.value?.selectionStart ?? props.modelValue.length;
    const end = input.value?.selectionEnd ?? start;
    emit(
        'update:modelValue',
        props.modelValue.slice(0, start) + emoji + props.modelValue.slice(end),
    );
    await nextTick();
    input.value?.focus();
    input.value?.setSelectionRange(start + emoji.length, start + emoji.length);
}
</script>
