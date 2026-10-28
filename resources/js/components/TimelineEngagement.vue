<template>
    <div class="mt-4 grid gap-3">
        <div
            v-if="showActions"
            class="flex flex-row items-center gap-2"
            :class="showDateTime ? 'justify-between' : ''"
        >
            <time
                v-if="showDateTime"
                class="rounded-xl border border-stone-200 bg-white px-2 py-1 text-left text-xs font-bold text-zinc-700"
                :datetime="post.datetime"
            >
                {{ post.date }}
            </time>

            <div class="flex flex-row items-center gap-2">
                <button
                    type="button"
                    class="inline-flex min-h-11 min-w-10 cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-stone-200 px-2.5 py-1.5 text-sm font-semibold transition-[background-color,box-shadow,translate] focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-60"
                    :class="
                        post.heartedByViewer
                            ? 'bg-blush text-rose-700'
                            : 'bg-white text-zinc-700 hover:bg-[#ffe0e5]'
                    "
                    :disabled="heartProcessing"
                    :aria-pressed="post.heartedByViewer"
                    :aria-label="'Heart ' + post.title"
                    @click="togglePostHeart"
                >
                    <AppIcon name="fa-solid fa-heart" />
                    <span>{{ post.heartsCount }}</span>
                </button>

                <button
                    v-if="allowComments"
                    type="button"
                    class="inline-flex min-h-11 min-w-10 cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-stone-200 bg-white px-2.5 py-1.5 text-sm font-semibold text-zinc-700 transition-[background-color,box-shadow,translate] hover:bg-mint focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-primary"
                    :aria-expanded="isCommentFormShown"
                    :aria-controls="`comment-form-${post.id}`"
                    @click="toggleCommentForm"
                >
                    <AppIcon name="fa-solid fa-comment" />
                    <span>{{ post.commentsCount }}</span>
                </button>
            </div>
        </div>

        <div
            v-if="
                allowComments &&
                showComments &&
                (post.commentsCount > 0 || isCommentFormShown)
            "
            class="mt-5 grid gap-4 border-t border-stone-200 pt-5 pb-3 sm:mt-6"
        >
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-xs font-semibold tracking-normal text-zinc-950">
                    Family comments
                </h3>
                <span
                    class="rounded-xl border border-stone-200 bg-butter px-2 py-0.5 text-xs font-semibold"
                >
                    {{ post.commentsCount }}
                </span>
            </div>

            <article
                v-for="comment in visibleComments"
                :key="comment.id"
                class="rounded-xl border border-stone-200 bg-white p-4"
            >
                <div
                    class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-zinc-600"
                >
                    <div
                        class="flex size-8 items-center justify-center rounded-xl border border-stone-200 bg-primary text-xs font-semibold text-white"
                    >
                        {{ userInitials(comment.author_fullname || 'Family') }}
                    </div>

                    <Link
                        :href="home({ query: queryForAuthor(comment.author) })"
                        class="font-semibold hover:underline"
                        :class="
                            activeAuthor === comment.author.toLowerCase()
                                ? 'text-primary'
                                : 'text-zinc-800'
                        "
                    >
                        {{ comment.author_fullname }}
                    </Link>

                    <span class="grow text-right font-semibold">{{
                        comment.createdAt
                    }}</span>
                </div>
                <p class="mt-4 text-sm leading-6 font-medium text-zinc-700">
                    {{ comment.content }}
                </p>
                <button
                    type="button"
                    class="mt-4 inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-xl border border-stone-200 px-2 py-1 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-60"
                    :class="
                        comment.heartedByViewer
                            ? 'bg-blush text-rose-700'
                            : 'bg-white text-zinc-600 hover:bg-[#ffe0e5]'
                    "
                    :disabled="commentHeartProcessing === comment.id"
                    :aria-pressed="comment.heartedByViewer"
                    @click="toggleCommentHeart(comment.id)"
                >
                    <AppIcon name="fa-solid fa-heart" />
                    <span>{{ comment.heartsCount }}</span>
                </button>
            </article>

            <button
                v-if="hiddenCommentCount > 0"
                type="button"
                class="w-fit cursor-pointer rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-semibold text-zinc-700 hover:bg-butter"
                @click="showAllComments = true"
            >
                Show {{ hiddenCommentCount }} more
                {{ hiddenCommentCount === 1 ? 'comment' : 'comments' }}
            </button>

            <form
                v-if="isCommentFormShown"
                :id="`comment-form-${post.id}`"
                class="grid gap-3 rounded-xl border border-stone-200 bg-butter p-4"
                :class="commentForm.processing ? 'opacity-60' : ''"
                @submit.prevent="submitComment"
            >
                <label class="sr-only" :for="`comment-${post.id}`"
                    >Add a comment</label
                >
                <textarea
                    :id="`comment-${post.id}`"
                    v-model="commentForm.content"
                    name="content"
                    rows="2"
                    placeholder="Add to the story..."
                    class="w-full resize-none rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm leading-6 font-medium text-zinc-700 placeholder:text-zinc-400"
                    required
                    :disabled="commentForm.processing"
                />
                <p
                    v-if="commentForm.errors.content"
                    class="border-l border-red-600 pl-2 text-sm font-bold text-red-700"
                >
                    {{ commentForm.errors.content }}
                </p>
                <button
                    type="submit"
                    class="cursor-pointer justify-self-end rounded-xl border border-stone-200 bg-primary px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="commentForm.processing"
                >
                    {{ commentForm.processing ? 'Posting…' : 'Post comment' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { store as storeComment } from '@/actions/App/Http/Controllers/CommentController';
import { toggle as toggleCommentHeartRoute } from '@/actions/App/Http/Controllers/CommentHeartController';
import { toggle as toggleHeart } from '@/actions/App/Http/Controllers/HeartController';
import AppIcon from '@/components/AppIcon.vue';
import { home } from '@/routes';
import type { PageProps, TimelinePost } from '@/types';

const props = withDefaults(
    defineProps<{
        post: TimelinePost;
        allowComments?: boolean;
        commentsOpen?: boolean;
        rail?: boolean;
        showActions?: boolean;
        showComments?: boolean;
        showDateTime?: boolean;
    }>(),
    {
        allowComments: true,
        commentsOpen: undefined,
        rail: false,
        showActions: true,
        showComments: true,
        showDateTime: false,
    },
);

const page = usePage<PageProps>();
const activeAuthor = computed(() => page.props.filters.author);

const queryForAuthor = (author: string) => {
    const username = author.toLowerCase();

    return {
        search: page.props.filters.search || undefined,
        tag: page.props.filters.tag || undefined,
        type: page.props.filters.type || undefined,
        author: activeAuthor.value === username ? undefined : username,
    };
};

const emit = defineEmits<{
    'update:commentsOpen': [value: boolean];
}>();

const showAllComments = ref(false);
const internalShowCommentForm = ref(false);
const heartProcessing = ref(false);
const commentHeartProcessing = ref<number | null>(null);
const commentForm = useForm({
    content: '',
});

const isCommentFormShown = computed(() => {
    return props.commentsOpen ?? internalShowCommentForm.value;
});

const toggleCommentForm = () => {
    const nextValue = !isCommentFormShown.value;

    if (props.commentsOpen === undefined) {
        internalShowCommentForm.value = nextValue;
    }

    emit('update:commentsOpen', nextValue);
};

const togglePostHeart = () => {
    router.visit(toggleHeart(props.post.id), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            heartProcessing.value = true;
        },
        onFinish: () => {
            heartProcessing.value = false;
        },
    });
};

const toggleCommentHeart = (commentId: number) => {
    router.visit(toggleCommentHeartRoute(commentId), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            commentHeartProcessing.value = commentId;
        },
        onFinish: () => {
            commentHeartProcessing.value = null;
        },
    });
};

const submitComment = () => {
    commentForm.post(storeComment.url(props.post.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            commentForm.reset('content');
        },
    });
};

const visibleComments = computed(() => {
    if (showAllComments.value) {
        return props.post.comments;
    }

    return props.post.comments.slice(0, 3);
});

const hiddenCommentCount = computed(() => {
    return Math.max(
        props.post.comments.length - visibleComments.value.length,
        0,
    );
});

const userInitials = (name: string) => {
    return name
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
};
</script>
