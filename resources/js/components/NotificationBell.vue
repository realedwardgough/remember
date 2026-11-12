<template>
    <Link
        :href="notificationIndex()"
        class="relative flex size-11 shrink-0 items-center justify-center rounded-full text-primary hover:bg-lilac focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        :aria-label="count ? `Notifications, ${count} unread` : 'Notifications'"
        :aria-current="
            page.url.split('?')[0] === '/notifications' ? 'page' : undefined
        "
    >
        <Bell :size="22" aria-hidden="true" />
        <span
            v-if="count"
            class="absolute -top-0.5 -right-1 min-w-5 rounded-full bg-primary px-1 text-center text-[10px] leading-5 font-semibold text-white"
            aria-hidden="true"
            >{{ count > 99 ? '99+' : count }}</span
        >
    </Link>
</template>
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';
import { index as notificationIndex } from '@/routes/activity';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const count = computed(() => page.props.unreadNotificationsCount);
</script>
