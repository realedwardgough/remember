<template>
    <form
        class="grid gap-5"
        :aria-busy="form.processing"
        @submit.prevent="submit"
    >
        <fieldset :disabled="form.processing" class="contents">
            <fieldset>
                <legend class="sr-only">Post type</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <label
                        v-for="type in postTypes"
                        :key="type"
                        class="relative flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl border px-2 text-sm font-medium has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-primary"
                        :class="
                            form.post_type === type
                                ? 'border-primary bg-lilac text-primary'
                                : 'border-stone-200 bg-white text-stone-600'
                        "
                    >
                        <input
                            v-model="form.post_type"
                            type="radio"
                            name="post_type"
                            :value="type"
                            class="sr-only"
                        />
                        <component
                            :is="typeIcons[type]"
                            :size="16"
                            aria-hidden="true"
                        />{{ type }}
                    </label>
                </div>
                <p v-if="form.errors.post_type" class="field-error mt-2">
                    {{ form.errors.post_type }}
                </p>
            </fieldset>
            <p
                v-if="form.post_type === 'Letter'"
                class="flex items-center gap-2 rounded-xl bg-lilac p-3 text-sm text-primary"
            >
                <LockKeyhole :size="17" class="shrink-0" /> Private letter —
                only you can read it.
            </p>
            <section aria-labelledby="photo-label">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h3 id="photo-label" class="text-sm font-semibold">
                        Photos
                        <span class="font-normal text-stone-400"
                            >(optional)</span
                        >
                    </h3>
                    <span class="text-xs text-stone-500"
                        >{{ photos.length }}/10</span
                    >
                </div>
                <input
                    id="composer-media"
                    ref="mediaInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    multiple
                    class="sr-only"
                    @change="addPhotos"
                />
                <div
                    v-if="photos.length"
                    class="mb-3 grid grid-cols-3 gap-2 sm:grid-cols-4"
                >
                    <div
                        v-for="(photo, index) in photos"
                        :key="photo.url"
                        class="relative aspect-square"
                    >
                        <img
                            :src="photo.url"
                            :alt="photo.file.name"
                            class="size-full rounded-xl object-cover"
                        />
                        <button
                            type="button"
                            class="absolute top-1 right-1 flex size-11 items-center justify-center rounded-full bg-white/95 text-stone-700 shadow-sm"
                            :aria-label="`Remove ${photo.file.name}`"
                            @click="removePhoto(index)"
                        >
                            <X :size="18" />
                        </button>
                    </div>
                </div>
                <button
                    type="button"
                    class="flex min-h-24 w-full flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-violet-200 bg-lilac/40 py-5 text-sm font-medium text-primary hover:bg-lilac"
                    :disabled="photos.length >= 10"
                    @click="mediaInput?.click()"
                >
                    <ImagePlus :size="25" />{{
                        photos.length
                            ? 'Add more photos'
                            : 'Add photos of this moment'
                    }}
                </button>
                <p class="mt-2 text-xs leading-5 text-stone-500">
                    Up to 10 photos, 10 MB each. JPG, PNG, WebP or GIF.
                </p>
                <p v-if="selectionError" role="alert" class="field-error mt-2">
                    {{ selectionError }}
                </p>
                <p
                    v-for="(error, key) in mediaErrors"
                    :key="key"
                    role="alert"
                    class="field-error mt-2"
                >
                    {{ error }}
                </p>
            </section>
            <label class="field-label" for="composer-title"
                >Title<input
                    id="composer-title"
                    v-model="form.title"
                    name="title"
                    class="field"
                    placeholder="Give this moment a name"
                    required
                    maxlength="255"
                    :aria-invalid="!!form.errors.title"
                    :aria-describedby="
                        form.errors.title ? 'title-error' : undefined
                    "
                /><span
                    v-if="form.errors.title"
                    id="title-error"
                    class="field-error"
                    >{{ form.errors.title }}</span
                ></label
            >
            <label class="field-label" for="composer-date"
                >Date<input
                    id="composer-date"
                    v-model="form.published_at"
                    name="published_at"
                    type="date"
                    class="field min-w-0"
                    required
                    :aria-invalid="!!form.errors.published_at"
                /><span v-if="form.errors.published_at" class="field-error">{{
                    form.errors.published_at
                }}</span></label
            >
            <label class="field-label" for="composer-content"
                >{{ form.post_type === 'Letter' ? 'Your letter' : 'The story' }}
                <textarea
                    id="composer-content"
                    v-model="form.content"
                    name="content"
                    :rows="form.post_type === 'Letter' ? 8 : 4"
                    class="field resize-y"
                    :placeholder="
                        form.post_type === 'Letter'
                            ? 'Something you want to remember, just for you…'
                            : 'What made this moment special?'
                    "
                    :aria-invalid="!!form.errors.content"
                /><span v-if="form.errors.content" class="field-error">{{
                    form.errors.content
                }}</span></label
            >
            <p class="-mt-3 text-xs leading-5 text-stone-500">
                Add #tags in your story to make moments easier to find.
            </p>
        </fieldset>
        <footer
            class="sticky bottom-0 -mx-5 border-t bg-canvas/95 px-5 pt-3 pb-[max(.75rem,env(safe-area-inset-bottom))] backdrop-blur sm:-mx-7 sm:px-7"
        >
            <div v-if="form.progress" class="mb-3" role="status">
                <p class="mb-1 text-xs text-primary">
                    Uploading photos… {{ form.progress.percentage ?? 0 }}%
                </p>
                <progress
                    :value="form.progress.percentage ?? 0"
                    max="100"
                    class="h-2 w-full accent-primary"
                />
            </div>
            <p v-if="submitError" role="alert" class="field-error mb-3">
                {{ submitError }}
            </p>
            <button
                type="submit"
                class="button-primary w-full"
                :disabled="form.processing"
            >
                <Check :size="18" />{{
                    form.processing
                        ? 'Saving your moment…'
                        : form.post_type === 'Letter'
                          ? 'Save private letter'
                          : 'Save memory'
                }}
            </button>
        </footer>
    </form>
</template>
<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    Flag,
    ImagePlus,
    LockKeyhole,
    Mail,
    X,
} from 'lucide-vue-next';
import { computed, inject, onBeforeUnmount, ref, watchEffect } from 'vue';
import { store } from '@/actions/App/Http/Controllers/PostController';
import { dialogStateKey } from '@/composables/useDialogState';
import type { PageProps, TimelinePostType } from '@/types';
const emit = defineEmits<{ close: [] }>();
const page = usePage<PageProps>();
const postTypes = computed(() => page.props.postTypes);
const dialog = inject(dialogStateKey, null);
const date = new Date();
const today = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
    .toISOString()
    .slice(0, 10);
const form = useForm({
    title: '',
    content: '',
    post_type: 'Memory' as TimelinePostType,
    published_at: today,
    media: [] as File[],
});
const mediaInput = ref<HTMLInputElement | null>(null);
const photos = ref<{ file: File; url: string }[]>([]);
const selectionError = ref('');
const submitError = ref('');
const typeIcons = {
    Memory: ImagePlus,
    Milestone: Flag,
    Event: CalendarDays,
    Letter: Mail,
};
const mediaErrors = computed(() =>
    Object.fromEntries(
        Object.entries(form.errors).filter(
            ([key]) => key === 'media' || key.startsWith('media.'),
        ),
    ),
);
watchEffect(() => {
    if (dialog) {
        dialog.dirty = form.isDirty;
        dialog.busy = form.processing;
    }
});
const addPhotos = (event: Event) => {
    const input = event.target as HTMLInputElement;
    selectionError.value = '';

    for (const file of Array.from(input.files ?? [])) {
        if (
            photos.value.some(
                (photo) =>
                    photo.file.name === file.name &&
                    photo.file.size === file.size &&
                    photo.file.lastModified === file.lastModified,
            )
        ) {
            continue;
        }

        if (photos.value.length >= 10) {
            selectionError.value = 'You can add up to 10 photos to one memory.';
            break;
        }

        if (
            !['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(
                file.type,
            )
        ) {
            selectionError.value = 'Choose JPG, PNG, WebP or GIF photos.';
            continue;
        }

        if (file.size > 10 * 1024 * 1024) {
            selectionError.value = 'Each photo must be 10 MB or smaller.';
            continue;
        }

        photos.value.push({ file, url: URL.createObjectURL(file) });
    }

    form.media = photos.value.map((photo) => photo.file);
    input.value = '';
};
const removePhoto = (index: number) => {
    URL.revokeObjectURL(photos.value[index].url);
    photos.value.splice(index, 1);
    form.media = photos.value.map((photo) => photo.file);
    selectionError.value = '';
    form.clearErrors();
};
const submit = () => {
    if (form.processing) {
        return;
    }

    submitError.value = '';
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();

            if (dialog) {
                dialog.dirty = false;
            }

            emit('close');
        },
        onNetworkError: () => {
            submitError.value =
                'The connection was interrupted. Your writing and photos are still here. Please try saving again.';

            return false;
        },
        onHttpException: () => {
            submitError.value =
                'Your memory could not be saved. Your writing and photos are still here. Please try again.';

            return false;
        },
        onError: () => {
            submitError.value =
                'Please check the highlighted fields. Your writing and photos are still here.';
        },
    });
};
const beforeUnload = (event: BeforeUnloadEvent) => {
    if (form.isDirty || form.processing) {
        event.preventDefault();
    }
};

if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', beforeUnload);
}

onBeforeUnmount(() => {
    photos.value.forEach((photo) => URL.revokeObjectURL(photo.url));

    if (typeof window !== 'undefined') {
        window.removeEventListener('beforeunload', beforeUnload);
    }
});
</script>
