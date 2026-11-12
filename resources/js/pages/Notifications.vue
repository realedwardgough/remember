<template>
    <BaseLayout>
        <template #sidebar>
            <Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
            />
        </template>
        <Head title="Notifications" />
        <section class="mx-auto max-w-2xl">
            <header
                class="mb-5 flex flex-wrap items-center justify-between gap-3"
            >
                <div>
                    <h1 class="story-font text-3xl">Notifications</h1>
                    <p class="mt-1 text-sm text-stone-500" role="status">
                        {{ page.props.unreadNotificationsCount }} unread
                    </p>
                </div>
                <button
                    v-if="page.props.unreadNotificationsCount"
                    type="button"
                    class="button-secondary min-h-11"
                    :disabled="readForm.processing"
                    @click="
                        readForm.put(readAll.url(), { preserveScroll: true })
                    "
                >
                    <CheckCheck :size="18" aria-hidden="true" />
                    Mark all as read
                </button>
            </header>
            <div
                v-if="activityNotifications.data.length"
                class="surface overflow-hidden"
            >
                <ul class="divide-y divide-stone-100">
                    <li
                        v-for="notification in activityNotifications.data"
                        :key="notification.id"
                    >
                        <Link
                            :href="openNotification(notification.id)"
                            method="post"
                            as="button"
                            class="flex w-full items-start gap-3 p-4 text-left transition-colors hover:bg-lilac focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary sm:p-5"
                            :class="
                                notification.read ? 'bg-white' : 'bg-lilac/50'
                            "
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-lilac text-primary"
                            >
                                <MessageCircle :size="20" aria-hidden="true" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm leading-6 break-words text-stone-800"
                                >
                                    <strong>{{ notification.author }}</strong>
                                    commented on
                                    <strong>{{
                                        notification.postTitle
                                    }}</strong>
                                </span>
                                <span
                                    class="mt-1 line-clamp-2 block text-sm leading-6 break-words text-stone-600"
                                    >{{ notification.preview }}</span
                                >
                                <time
                                    :datetime="notification.createdAt"
                                    class="mt-2 block text-xs text-stone-500"
                                    >{{ notification.timeAgo }}</time
                                >
                            </span>
                            <span
                                v-if="!notification.read"
                                class="mt-2 size-2 shrink-0 rounded-full bg-primary"
                            >
                                <span class="sr-only">Unread</span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
            <div v-else class="surface px-5 py-12 text-center">
                <Bell
                    :size="32"
                    class="mx-auto mb-4 text-primary"
                    aria-hidden="true"
                />
                <h2 class="text-lg font-semibold">No notifications yet</h2>
                <p class="mt-2 text-sm leading-6 text-stone-500">
                    When someone comments on your memories, you’ll see it here.
                </p>
            </div>
            <nav
                v-if="
                    activityNotifications.prev_page_url ||
                    activityNotifications.next_page_url
                "
                aria-label="Notification pages"
                class="mt-5 flex justify-between gap-3"
            >
                <Link
                    v-if="activityNotifications.prev_page_url"
                    :href="activityNotifications.prev_page_url"
                    class="button-secondary min-h-11"
                    >Newer</Link
                >
                <Link
                    v-if="activityNotifications.next_page_url"
                    :href="activityNotifications.next_page_url"
                    class="button-secondary ml-auto min-h-11"
                    >Older</Link
                >
            </nav>
        </section>
        <Modal
            :show="activeModal === 'post'"
            mobile-fullscreen
            title="Add a memory"
            eyebrow="New memory"
            @close="activeModal = null"
        >
            <PostForm @close="activeModal = null" />
        </Modal>
        <Modal
            :show="activeModal === 'search'"
            title="Search the timeline"
            eyebrow="Find memories"
            @close="activeModal = null"
        >
            <Search @close="activeModal = null" />
        </Modal>
    </BaseLayout>
</template>
<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Bell, CheckCheck, MessageCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import Modal from '@/components/Modal.vue';
import PostForm from '@/components/PostForm.vue';
import Search from '@/components/Search.vue';
import Sidebar from '@/components/Sidebar.vue';
import BaseLayout from '@/layouts/BaseLayout.vue';
import { open as openNotification, readAll } from '@/routes/activity';
import type { PageProps } from '@/types';

defineProps<{
    activityNotifications: {
        data: {
            id: number;
            author: string;
            preview: string;
            postTitle: string;
            createdAt: string;
            timeAgo: string;
            read: boolean;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const page = usePage<PageProps>();
const readForm = useForm({});
const activeModal = ref<'post' | 'search' | null>(null);
</script>
