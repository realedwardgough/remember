<template>
    <div class="contents">
        <Form
            v-bind="update.form(post.id)"
            class="grid gap-5 inert:pointer-events-none inert:opacity-60"
            disable-while-processing
            preserve-scroll
            @success="emit('close')"
            v-slot="{ errors, processing }"
        >
            <label class="grid gap-2 text-sm font-semibold text-zinc-950">
                Title
                <input
                    name="title"
                    type="text"
                    :value="post.title"
                    required
                    class="min-h-12 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case"
                />
                <span
                    v-if="errors.title"
                    class="text-sm font-normal text-red-600"
                >
                    {{ errors.title }}
                </span>
            </label>

            <label class="grid gap-2 text-sm font-semibold text-zinc-950">
                Content
                <textarea
                    name="content"
                    rows="6"
                    :value="post.content"
                    class="rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-medium normal-case"
                ></textarea>
                <span
                    v-if="errors.content"
                    class="text-sm font-normal text-red-600"
                >
                    {{ errors.content }}
                </span>
            </label>

            <fieldset class="grid gap-2">
                <legend class="text-sm font-semibold text-zinc-950">
                    Post type
                </legend>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <label
                        v-for="type in postTypes"
                        :key="type"
                        class="inline-flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl border border-stone-200 px-3 py-2 text-sm font-semibold transition-colors select-none has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-primary"
                        :class="[
                            typeClasses[type],
                            selectedPostType === type
                                ? 'ring-2 ring-primary'
                                : 'opacity-65 hover:opacity-100',
                        ]"
                    >
                        <input
                            v-model="selectedPostType"
                            type="radio"
                            name="post_type"
                            :value="type"
                            class="sr-only"
                        />
                        <AppIcon :name="typeIcons[type]" />
                        <span>{{ type }}</span>
                    </label>
                </div>
                <span
                    v-if="errors.post_type"
                    class="text-sm font-normal text-red-600"
                >
                    {{ errors.post_type }}
                </span>
            </fieldset>

            <label class="grid gap-2 text-sm font-semibold text-zinc-950">
                Post date
                <input
                    name="published_at"
                    type="date"
                    :value="post.publishedAt"
                    required
                    class="min-h-12 rounded-xl border border-stone-200 bg-white px-4 py-3 text-base font-semibold normal-case"
                />
                <span
                    v-if="errors.published_at"
                    class="text-sm font-normal text-red-600"
                >
                    {{ errors.published_at }}
                </span>
            </label>

            <div
                class="flex flex-col-reverse gap-3 border-t border-stone-200 pt-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <button
                    type="button"
                    class="inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="deleteProcessing"
                    @click="showDeleteConfirmation = true"
                >
                    <AppIcon name="fa-solid fa-trash" />
                    {{ deleteProcessing ? 'Deleting...' : 'Delete post' }}
                </button>

                <div class="flex items-center justify-end gap-3">
                    <Link
                        v-if="cancelHref"
                        :href="cancelHref"
                        class="inline-flex min-h-11 cursor-pointer items-center rounded-xl border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-butter"
                    >
                        Cancel
                    </Link>
                    <button
                        v-else
                        type="button"
                        class="inline-flex min-h-11 cursor-pointer items-center rounded-xl border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-butter"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex min-h-11 cursor-pointer items-center rounded-xl border border-stone-200 bg-primary px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="processing"
                    >
                        {{ processing ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </div>
        </Form>

        <ConfirmModal
            :show="showDeleteConfirmation"
            title="Delete this post?"
            eyebrow="Permanent action"
            message="This post and its attached media, comments, and reactions will be permanently removed from the timeline."
            confirm-label="Delete post"
            processing-label="Deleting…"
            :processing="deleteProcessing"
            @close="closeDeleteConfirmation"
            @confirm="deletePost"
        />
    </div>
</template>

<script setup lang="ts">
import type { LinkComponentBaseProps } from '@inertiajs/core';
import { Form, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import { destroy, update } from '@/routes/posts';
import type { TimelinePostType } from '@/types';

export type EditableTimelinePost = {
    id: number;
    title: string;
    content: string;
    postType: TimelinePostType;
    publishedAt: string;
};

const emit = defineEmits<{
    close: [];
}>();

const props = defineProps<{
    post: EditableTimelinePost;
    postTypes: TimelinePostType[];
    cancelHref?: LinkComponentBaseProps['href'];
}>();

const deleteProcessing = ref(false);
const showDeleteConfirmation = ref(false);
const selectedPostType = ref<TimelinePostType>(props.post.postType);

const typeClasses: Record<TimelinePostType, string> = {
    Memory: 'bg-white text-zinc-800',
    Milestone: 'bg-butter text-zinc-800',
    Event: 'bg-mint text-zinc-800',
    Letter: 'bg-lilac text-zinc-800',
};

const typeIcons: Record<TimelinePostType, string> = {
    Memory: 'fa-solid fa-camera-retro',
    Milestone: 'fa-solid fa-star',
    Event: 'fa-solid fa-calendar-day',
    Letter: 'fa-solid fa-envelope-open-text',
};

const closeDeleteConfirmation = () => {
    if (!deleteProcessing.value) {
        showDeleteConfirmation.value = false;
    }
};

const deletePost = () => {
    router.delete(destroy.url(props.post.id), {
        preserveScroll: true,
        onStart: () => {
            deleteProcessing.value = true;
        },
        onSuccess: () => {
            showDeleteConfirmation.value = false;
            emit('close');
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
};
</script>
