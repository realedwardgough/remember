<template>
    <Form
        v-bind="store.form()"
        reset-on-success
        disable-while-processing
        preserve-scroll
        @success="handleSuccess"
        class="h-fit self-start inert:pointer-events-none inert:opacity-60"
        v-slot="{ errors, processing, progress, wasSuccessful }"
    >
        <div class="flex items-start gap-4">
            <div
                class="hidden size-11 shrink-0 items-center justify-center border-3 border-zinc-950 bg-[#77c8b5] text-sm font-black text-zinc-950 shadow-[3px_3px_0_0_#18181b] sm:flex"
                aria-hidden="true"
            >
                {{ userInitials }}
            </div>

            <div class="grid min-w-0 flex-1 gap-3.5">
                <label for="composer-title" class="sr-only">Title</label>
                <input
                    id="composer-title"
                    name="title"
                    type="text"
                    :placeholder="
                        selectedType === 'Letter'
                            ? 'Give your letter a title'
                            : 'What memory are we preserving?'
                    "
                    class="min-h-12 w-full border-3 border-zinc-950 bg-white px-4 py-3 text-base font-bold text-zinc-950 transition-[box-shadow,translate] outline-none placeholder:font-medium placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:shadow-[4px_4px_0_0_#e06573]"
                    required
                />
                <p
                    v-if="errors.title"
                    class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700"
                >
                    {{ errors.title }}
                </p>

                <label for="composer-content" class="sr-only">Memory</label>
                <textarea
                    id="composer-content"
                    name="content"
                    :rows="selectedType === 'Letter' ? 9 : 4"
                    :placeholder="
                        selectedType === 'Letter'
                            ? 'Write your private letter...'
                            : 'Write a short update, milestone, or family moment...'
                    "
                    class="w-full resize-y border-3 border-zinc-950 bg-white px-4 py-3 text-sm leading-6 font-medium text-zinc-800 transition-[box-shadow,translate] outline-none placeholder:text-zinc-400 focus:-translate-x-0.5 focus:-translate-y-0.5 focus:shadow-[4px_4px_0_0_#e06573]"
                    :class="
                        selectedType === 'Letter' ? 'min-h-[300px]' : 'min-h-28'
                    "
                />
                <p
                    v-if="errors.content"
                    class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700"
                >
                    {{ errors.content }}
                </p>
            </div>
        </div>

        <div class="mt-5 grid gap-5 border-t-3 border-zinc-950 pt-5">
            <div class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-start">
                <fieldset class="flex flex-wrap gap-2">
                    <legend class="sr-only">Post type</legend>
                    <label
                        v-for="type in postTypes"
                        :key="type"
                        class="inline-flex min-h-10 cursor-pointer items-center gap-2 border-2 border-zinc-950 px-3 py-2 text-sm font-black transition-[box-shadow,translate] select-none"
                        :class="[
                            typeClasses[type],
                            selectedType === type
                                ? '-translate-x-0.5 -translate-y-0.5 shadow-[4px_4px_0_0_#18181b]'
                                : 'opacity-65 hover:opacity-100',
                        ]"
                    >
                        <input
                            type="radio"
                            name="post_type"
                            :value="type"
                            class="size-4 accent-zinc-950"
                            v-model="selectedType"
                        />
                        <i :class="typeIcons[type]"></i>
                        <span>{{ type }}</span>
                    </label>
                </fieldset>

                <div class="mt-2 flex flex-row justify-end gap-2 sm:mt-0">
                    <button
                        type="button"
                        class="inline-flex min-h-10 cursor-pointer items-center justify-center gap-2 border-3 border-zinc-950 bg-white px-3 py-2 text-sm font-black text-zinc-950 shadow-[3px_3px_0_0_#18181b] hover:bg-[#fff0b8] sm:w-auto"
                        :aria-expanded="showDatePicker"
                        aria-controls="composer-date-panel"
                        @click="showDatePicker = !showDatePicker"
                    >
                        <i class="fa-solid fa-calendar"></i>
                    </button>
                    <label
                        for="composer-media"
                        class="inline-flex min-h-10 cursor-pointer items-center justify-center gap-2 border-3 border-zinc-950 bg-white px-3 py-2 text-sm font-black text-zinc-950 shadow-[3px_3px_0_0_#18181b] hover:bg-[#b9eadf]"
                    >
                        <i class="fa-solid fa-upload"></i>
                        <span class="md:hidden">Upload</span>
                    </label>
                    <input
                        id="composer-media"
                        ref="mediaInput"
                        type="file"
                        name="media[]"
                        accept="image/*"
                        multiple
                        class="sr-only"
                        @change="updateSelectedFiles"
                    />
                    <button
                        type="submit"
                        class="min-h-10 cursor-pointer border-3 border-zinc-950 bg-[#e06573] px-4 py-2 text-sm font-black text-white shadow-[4px_4px_0_0_#18181b] hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-[6px_6px_0_0_#18181b] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="processing"
                    >
                        {{ processing ? 'Posting...' : 'Post' }}
                    </button>
                </div>
            </div>

            <p
                v-if="errors.post_type"
                class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700"
            >
                {{ errors.post_type }}
            </p>

            <input
                v-if="!showDatePicker"
                type="hidden"
                name="published_at"
                :value="today"
            />

            <div
                v-if="showDatePicker"
                id="composer-date-panel"
                class="grid gap-3 border-3 border-zinc-950 bg-[#fff0b8] p-4 shadow-[4px_4px_0_0_#f8cc5d] sm:max-w-xs"
            >
                <label
                    for="composer-published-at"
                    class="text-sm font-black text-zinc-950 uppercase"
                >
                    Timeline date
                </label>
                <input
                    id="composer-published-at"
                    type="date"
                    name="published_at"
                    :value="today"
                    class="min-h-10 border-3 border-zinc-950 bg-white px-3 py-2 text-sm font-bold text-zinc-800 outline-none focus:shadow-[3px_3px_0_0_#e06573]"
                    required
                />
                <p class="text-xs leading-5 text-slate-500">
                    Use this for scans, milestones, or older memories that
                    should appear on a specific day.
                </p>
                <p v-if="errors.published_at" class="text-sm text-red-600">
                    {{ errors.published_at }}
                </p>
            </div>

            <div
                v-if="selectedFiles.length"
                class="grid gap-3 border-3 border-zinc-950 bg-[#b9eadf] p-4 shadow-[4px_4px_0_0_#77c8b5]"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-700">
                        {{ selectedFiles.length }}
                        {{ selectedFiles.length === 1 ? 'file' : 'files' }}
                        selected
                    </p>
                    <button
                        type="button"
                        class="border-2 border-zinc-950 bg-white px-2 py-1 text-xs font-black text-zinc-700 hover:bg-[#f8cc5d]"
                        @click="clearSelectedFiles"
                    >
                        Clear
                    </button>
                </div>

                <ul class="grid gap-2">
                    <li
                        v-for="file in selectedFiles"
                        :key="`${file.name}-${file.size}`"
                        class="flex min-w-0 items-center gap-2 text-sm text-slate-600"
                    >
                        <i class="fa-solid fa-paperclip text-slate-400"></i>
                        <span class="min-w-0 flex-1 truncate">{{
                            file.name
                        }}</span>
                        <span class="shrink-0 text-xs text-slate-400">
                            {{ formatFileSize(file.size) }}
                        </span>
                    </li>
                </ul>
            </div>

            <p v-if="errors.media" class="text-sm text-red-600">
                {{ errors.media }}
            </p>
            <p v-if="errors['media.0']" class="text-sm text-red-600">
                {{ errors['media.0'] }}
            </p>

            <progress
                v-if="progress"
                :value="progress.percentage"
                max="100"
                class="h-3 w-full overflow-hidden border-2 border-zinc-950"
            >
                {{ progress.percentage }}%
            </progress>

            <p
                v-if="wasSuccessful"
                class="text-sm font-medium text-emerald-700"
            >
                Post saved.
            </p>
        </div>
    </Form>
</template>

<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/PostController';
import type { PageProps, TimelinePostType } from '@/types';

const emit = defineEmits<{
    close: [];
}>();

const page = usePage<PageProps>();
const postTypes = computed(() => page.props.postTypes);
const showDatePicker = ref(false);
const mediaInput = ref<HTMLInputElement | null>(null);
const selectedFiles = ref<File[]>([]);
const selectedType = ref<TimelinePostType>('Memory');

const typeClasses: Record<TimelinePostType, string> = {
    Memory: 'bg-white text-zinc-800',
    Milestone: 'bg-[#fff0b8] text-zinc-800',
    Event: 'bg-[#b9eadf] text-zinc-800',
    Letter: 'bg-[#eee6ff] text-zinc-800',
};

const typeIcons: Record<TimelinePostType, string> = {
    Memory: 'fa-solid fa-camera-retro',
    Milestone: 'fa-solid fa-star',
    Event: 'fa-solid fa-calendar-day',
    Letter: 'fa-solid fa-envelope-open-text',
};

const today = computed(() => {
    const currentDate = new Date();
    const timezoneOffset = currentDate.getTimezoneOffset() * 60000;

    return new Date(currentDate.getTime() - timezoneOffset)
        .toISOString()
        .slice(0, 10);
});

const updateSelectedFiles = (event: Event) => {
    const input = event.target as HTMLInputElement;

    selectedFiles.value = Array.from(input.files ?? []);
};

const clearSelectedFiles = () => {
    selectedFiles.value = [];

    if (mediaInput.value) {
        mediaInput.value.value = '';
    }
};

const handleSuccess = () => {
    clearSelectedFiles();
    emit('close');
};

const formatFileSize = (bytes: number) => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const userInitials = computed(() => {
    const name = page.props.auth.user?.name ?? 'Family';

    return name
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
});
</script>
