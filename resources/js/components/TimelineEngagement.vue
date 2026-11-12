<template>
    <div class="mt-2 grid gap-1 lg:mt-4 lg:gap-3" data-post-engagement>
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
                    class="inline-flex min-h-11 min-w-10 cursor-pointer items-center justify-center gap-1.5 rounded-xl px-2.5 py-1.5 text-sm font-semibold transition-colors focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-60 lg:border lg:border-stone-200"
                    :class="
                        post.heartedByViewer
                            ? 'text-rose-700'
                            : 'text-zinc-700 hover:text-rose-700'
                    "
                    :disabled="heartProcessing"
                    :aria-pressed="post.heartedByViewer"
                    :aria-label="'Heart ' + post.title"
                    @click="emit('toggleHeart')"
                >
                    <Heart
                        :size="18"
                        :fill="post.heartedByViewer ? 'currentColor' : 'none'"
                        aria-hidden="true"
                    />
                    <span>{{ post.heartsCount }}</span>
                </button>

                <button
                    v-if="allowComments"
                    type="button"
                    class="inline-flex min-h-11 min-w-10 cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-transparent px-2.5 py-1.5 text-sm font-semibold text-zinc-700 transition-colors hover:bg-mint focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-primary lg:border lg:border-stone-200 lg:bg-white"
                    :aria-expanded="isCommentFormShown"
                    :aria-controls="`comment-form-${post.id}`"
                    @click="toggleCommentForm"
                >
                    <AppIcon name="fa-solid fa-comment" />
                    <span>{{ commentTotal }}</span>
                </button>
            </div>
        </div>

        <div
            v-if="
                allowComments &&
                discussionOpen &&
                (commentTotal > 0 || isCommentFormShown)
            "
            class="grid gap-0 border-t border-stone-200 pt-2 lg:mt-6 lg:gap-4 lg:pt-5 lg:pb-3"
        >
            <div class="flex items-center justify-between gap-3 pb-1 lg:pb-0">
                <h3 class="text-xs font-semibold tracking-normal text-zinc-950">
                    Family comments
                </h3>
                <span
                    class="text-xs font-semibold text-stone-500 lg:rounded-xl lg:border lg:border-stone-200 lg:bg-butter lg:px-2 lg:py-0.5 lg:text-inherit"
                >
                    {{ commentTotal }}
                </span>
            </div>

            <p
                v-if="commentsLoading"
                role="status"
                class="py-2 text-sm text-stone-500"
            >
                Loading comments…
            </p>
            <div
                v-if="commentsError"
                role="alert"
                class="py-2 text-sm text-red-700"
            >
                {{ commentsError }}
                <button
                    type="button"
                    class="ml-2 underline"
                    @click="loadComments()"
                >
                    Retry
                </button>
            </div>
            <button
                v-if="previousPage !== null"
                type="button"
                class="button-secondary justify-self-start"
                :disabled="commentsLoading"
                @click="loadComments(previousPage, 'before')"
            >
                Load earlier comments
            </button>

            <article
                v-for="comment in loadedComments"
                :key="comment.id"
                :id="`comment-${comment.id}`"
                tabindex="-1"
                class="min-w-0 scroll-mt-20 border-b border-stone-100 py-2 target:rounded-xl target:bg-lilac target:px-3 target:ring-2 target:ring-primary lg:rounded-xl lg:border lg:border-stone-200 lg:bg-white lg:p-4"
            >
                <div
                    class="flex items-center gap-2 text-xs text-zinc-600 lg:flex-wrap lg:gap-x-3 lg:gap-y-2"
                >
                    <div
                        class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white lg:size-8 lg:rounded-xl lg:border lg:border-stone-200"
                    >
                        {{ userInitials(comment.author_fullname || 'Family') }}
                    </div>

                    <Link
                        :href="home({ query: queryForAuthor(comment.author) })"
                        class="min-w-0 flex-1 truncate font-semibold hover:underline lg:flex-none"
                        :class="
                            activeAuthor === comment.author.toLowerCase()
                                ? 'text-primary'
                                : 'text-zinc-800'
                        "
                    >
                        {{ comment.author_fullname }}
                    </Link>

                    <span
                        class="ml-auto shrink-0 text-right text-[10px] font-medium lg:grow lg:text-xs lg:font-semibold"
                        >{{ comment.createdAt }}</span
                    >
                </div>
                <p
                    class="mt-1 text-sm leading-6 break-words whitespace-pre-line text-zinc-700 lg:mt-4 lg:font-medium"
                >
                    {{ comment.content }}
                </p>
                <button
                    type="button"
                    class="inline-flex min-h-11 min-w-11 cursor-pointer items-center gap-1.5 rounded-xl px-2 py-1 text-xs font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60 lg:mt-4 lg:border lg:border-stone-200"
                    :class="
                        comment.heartedByViewer
                            ? 'text-rose-700'
                            : 'text-zinc-600 hover:text-rose-700'
                    "
                    :disabled="commentHeartProcessing === comment.id"
                    :aria-pressed="comment.heartedByViewer"
                    :aria-label="`Heart comment by ${comment.author_fullname}`"
                    @click="toggleCommentHeart(comment.id)"
                >
                    <Heart
                        :size="16"
                        :fill="
                            comment.heartedByViewer ? 'currentColor' : 'none'
                        "
                        aria-hidden="true"
                    />
                    <span>{{ comment.heartsCount }}</span>
                </button>
            </article>

            <button
                v-if="nextPage !== null"
                type="button"
                class="button-secondary justify-self-start"
                :disabled="commentsLoading"
                @click="loadComments(nextPage, 'after')"
            >
                Load more comments
            </button>

            <form
                v-if="isCommentFormShown"
                :id="`comment-form-${post.id}`"
                class="grid gap-2 pt-3 lg:gap-3 lg:rounded-xl lg:border lg:border-stone-200 lg:bg-butter lg:p-4"
                :class="commentForm.processing ? 'opacity-60' : ''"
                @submit.prevent="submitComment"
            >
                <label class="sr-only" :for="`comment-input-${post.id}`"
                    >Add a comment</label
                >
                <textarea
                    :id="`comment-input-${post.id}`"
                    v-model="commentForm.content"
                    name="content"
                    rows="2"
                    placeholder="Add to the story..."
                    class="w-full resize-none rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm leading-6 font-medium text-zinc-700 placeholder:text-zinc-400 lg:px-4 lg:py-3"
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
                    class="min-h-11 cursor-pointer justify-self-end rounded-xl border border-stone-200 bg-primary px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
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
import { Heart } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { store as storeComment } from '@/actions/App/Http/Controllers/CommentController';
import { toggle as toggleCommentHeartRoute } from '@/actions/App/Http/Controllers/CommentHeartController';
import AppIcon from '@/components/AppIcon.vue';
import { home } from '@/routes';
import type { PageProps, TimelineComment, TimelinePost } from '@/types';

const props = withDefaults(
    defineProps<{
        post: TimelinePost;
        expanded?: boolean;
        heartProcessing?: boolean;
        openPost?: string;
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
    toggleHeart: [];
}>();

const internalShowCommentForm = ref(false);
const commentHeartProcessing = ref<number | null>(null);
const commentForm = useForm({
    content: '',
});

const isCommentFormShown = computed(() => {
    return props.commentsOpen ?? internalShowCommentForm.value;
});

const toggleCommentForm = () => {
    if (props.openPost) {
        router.visit(props.openPost);

        return;
    }

    const nextValue = !isCommentFormShown.value;

    if (props.commentsOpen === undefined) {
        internalShowCommentForm.value = nextValue;
    }

    emit('update:commentsOpen', nextValue);
};

const toggleCommentHeart = (commentId: number) => {
    router.visit(toggleCommentHeartRoute(commentId), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            commentHeartProcessing.value = commentId;
        },
        onSuccess: () => {
            void loadComments(1, 'refresh', commentId);
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

const loadedComments = ref<TimelineComment[]>(props.post.comments ?? []);
const commentTotal = ref(props.post.commentsCount);
const commentsLoading = ref(false);
const commentsError = ref('');
const commentsLoaded = ref(false);
const previousPage = ref<number | null>(null);
const nextPage = ref<number | null>(null);
const discussionOpen = computed(
    () =>
        props.allowComments &&
        props.showComments &&
        (props.expanded || isCommentFormShown.value),
);
let mounted = false;
let generation = 0;
let controller: AbortController | undefined;

type CommentPage = {
    data: TimelineComment[];
    total: number;
    previous_page: number | null;
    next_page: number | null;
};
const loadComments = async (
    pageNumber = 1,
    mode: 'replace' | 'before' | 'after' | 'refresh' = 'replace',
    focusId?: number,
) => {
    if (!mounted || !discussionOpen.value || !props.post.commentsUrl) {
        return;
    }

    controller?.abort();
    controller = new AbortController();
    const requestController = controller;
    const current = ++generation;
    commentsLoading.value = true;
    commentsError.value = '';
    const query = focusId ? `focus_id=${focusId}` : `page=${pageNumber}`;

    try {
        const response = await fetch(`${props.post.commentsUrl}?${query}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: requestController.signal,
        });

        if (!response.ok) {
            throw new Error('Comments unavailable');
        }

        const result: CommentPage = await response.json();

        if (current !== generation || requestController.signal.aborted) {
            return;
        }

        const items =
            mode === 'replace'
                ? result.data
                : [...loadedComments.value, ...result.data];
        loadedComments.value = [
            ...new Map(items.map((comment) => [comment.id, comment])).values(),
        ].sort((a, b) => a.id - b.id);
        commentTotal.value = result.total;
        commentsLoaded.value = true;

        if (mode === 'replace' || mode === 'before') {
            previousPage.value = result.previous_page;
        }

        if (mode === 'replace' || mode === 'after') {
            nextPage.value = result.next_page;
        }

        if (focusId && mode === 'replace') {
            await nextTick();
            document
                .getElementById(`comment-${focusId}`)
                ?.scrollIntoView({ block: 'center' });
        }
    } catch {
        if (current === generation && !requestController.signal.aborted) {
            commentsError.value = 'Comments could not be loaded.';
        }
    } finally {
        if (current === generation) {
            commentsLoading.value = false;
        }
    }
};
const openComments = () => {
    if (mounted && discussionOpen.value && !commentsLoaded.value) {
        const anchor = /^#comment-(\d+)$/.exec(window.location.hash);
        void loadComments(1, 'replace', anchor ? Number(anchor[1]) : undefined);
    }
};
watch(discussionOpen, (open) => {
    if (open) {
        openComments();
    } else {
        generation++;
        controller?.abort();
        commentsLoading.value = false;
    }
});
watch(
    () => props.post.commentsCount,
    (count) => {
        commentTotal.value = count;
        commentsLoaded.value = false;

        if (discussionOpen.value) {
            void loadComments(Math.max(1, Math.ceil(count / 20)));
        }
    },
);
onMounted(() => {
    mounted = true;
    openComments();
});
onBeforeUnmount(() => {
    generation++;
    controller?.abort();
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
