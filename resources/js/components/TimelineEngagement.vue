<template>
    <div class="grid gap-3" :class="showActions ? 'sm:mt-3' : ''">
        <div
            v-if="showActions"
            class="flex flex-row items-center gap-2 sm:flex-col"
            :class="showDateTime ? 'justify-between' : ''"
        >
            <time
                v-if="showDateTime"
                class="border-2 border-zinc-950 bg-white px-2 py-1 text-left text-xs font-bold text-zinc-700 shadow-[2px_2px_0_0_#18181b]"
                :datetime="post.datetime"
            >
                {{ post.date }}
            </time>

            <div class="flex flex-row items-center gap-2 sm:flex-col">
                <button
                    type="button"
                    class="inline-flex min-h-9 min-w-10 cursor-pointer items-center justify-center gap-1.5 border-2 border-zinc-950 px-2.5 py-1.5 text-sm font-black shadow-[3px_3px_0_0_#18181b] transition-[background-color,box-shadow,translate] hover:-translate-x-0.5 hover:-translate-y-0.5 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-[#77c8b5] active:translate-x-0.5 active:translate-y-0.5 active:shadow-[1px_1px_0_0_#18181b] disabled:cursor-not-allowed disabled:opacity-60"
                    :class="
                        post.heartedByViewer
                            ? 'bg-[#e06573] text-white'
                            : 'bg-white text-zinc-700 hover:bg-[#ffe0e5]'
                    "
                    :disabled="heartProcessing"
                    :aria-pressed="post.heartedByViewer"
                    :aria-label="'Heart ' + post.title"
                    @click="togglePostHeart"
                >
                    <i class="fa-solid fa-heart"></i>
                    <span>{{ post.heartsCount }}</span>
                </button>

                <button
                    v-if="allowComments"
                    type="button"
                    class="inline-flex min-h-9 min-w-10 cursor-pointer items-center justify-center gap-1.5 border-2 border-zinc-950 bg-white px-2.5 py-1.5 text-sm font-black text-zinc-700 shadow-[3px_3px_0_0_#18181b] transition-[background-color,box-shadow,translate] hover:-translate-x-0.5 hover:-translate-y-0.5 hover:bg-[#b9eadf] focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-[#e06573] active:translate-x-0.5 active:translate-y-0.5 active:shadow-[1px_1px_0_0_#18181b]"
                    :aria-expanded="isCommentFormShown"
                    :aria-controls="`comment-form-${post.id}`"
                    @click="toggleCommentForm"
                >
                    <i class="fa-solid fa-comment"></i>
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
            class="mt-5 grid gap-4 border-t-3 border-zinc-950 pt-5 pb-3 sm:mt-6 sm:ml-4"
        >
            <div class="flex items-center justify-between gap-3">
                <h3
                    class="text-xs font-black tracking-[0.18em] text-zinc-950 uppercase"
                >
                    Family comments
                </h3>
                <span
                    class="border-2 border-zinc-950 bg-[#f8cc5d] px-2 py-0.5 text-xs font-black shadow-[2px_2px_0_0_#18181b]"
                >
                    {{ post.commentsCount }}
                </span>
            </div>

            <article
                v-for="comment in visibleComments"
                :key="comment.id"
                class="border-3 border-zinc-950 bg-white p-4 shadow-[4px_4px_0_0_#77c8b5]"
            >
                <div
                    class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-zinc-600"
                >
                    <div
                        class="flex size-8 items-center justify-center border-2 border-zinc-950 bg-[#e06573] text-xs font-black text-white shadow-[2px_2px_0_0_#18181b]"
                    >
                        {{ userInitials(comment.author_fullname || 'Family') }}
                    </div>

                    <Link
                        :href="home({ query: queryForAuthor(post.author) })"
                        class="font-black hover:underline"
                        :class="
                            activeAuthor === post.author.toLowerCase()
                                ? 'text-[#e06573]'
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
                    class="mt-4 inline-flex min-h-8 cursor-pointer items-center gap-1.5 border-2 border-zinc-950 px-2 py-1 text-xs font-black shadow-[2px_2px_0_0_#18181b] hover:-translate-x-0.5 hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                    :class="
                        comment.heartedByViewer
                            ? 'bg-[#e06573] text-white'
                            : 'bg-white text-zinc-600 hover:bg-[#ffe0e5]'
                    "
                    :disabled="commentHeartProcessing === comment.id"
                    :aria-pressed="comment.heartedByViewer"
                    @click="toggleCommentHeart(comment.id)"
                >
                    <i class="fa-solid fa-heart"></i>
                    <span>{{ comment.heartsCount }}</span>
                </button>
            </article>

            <button
                v-if="hiddenCommentCount > 0"
                type="button"
                class="w-fit cursor-pointer border-2 border-zinc-950 bg-white px-3 py-1.5 text-sm font-black text-zinc-700 shadow-[2px_2px_0_0_#18181b] hover:bg-[#fff0b8]"
                @click="showAllComments = true"
            >
                Show {{ hiddenCommentCount }} more
                {{ hiddenCommentCount === 1 ? 'comment' : 'comments' }}
            </button>

            <form
                v-if="isCommentFormShown"
                :id="`comment-form-${post.id}`"
                class="grid gap-3 border-3 border-zinc-950 bg-[#fff0b8] p-4 shadow-[4px_4px_0_0_#f8cc5d]"
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
                    class="w-full resize-none border-3 border-zinc-950 bg-white px-4 py-3 text-sm leading-6 font-medium text-zinc-700 placeholder:text-zinc-400 focus:shadow-[4px_4px_0_0_#e06573] focus:outline-none"
                    required
                    :disabled="commentForm.processing"
                />
                <p
                    v-if="commentForm.errors.content"
                    class="border-l-4 border-red-600 pl-2 text-sm font-bold text-red-700"
                >
                    {{ commentForm.errors.content }}
                </p>
                <button
                    type="submit"
                    class="cursor-pointer justify-self-end border-3 border-zinc-950 bg-[#e06573] px-4 py-2 text-sm font-black text-white shadow-[4px_4px_0_0_#18181b] hover:-translate-x-0.5 hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
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
