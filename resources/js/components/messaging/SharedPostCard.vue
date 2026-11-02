<template>
    <Link
        v-if="share.status === 'available'"
        :href="share.url"
        class="mt-2 flex max-w-80 items-start gap-3 rounded-2xl border bg-white p-4 text-stone-700 hover:bg-lilac"
        :aria-label="`Open shared timeline post: ${share.title}`"
    >
        <span
            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-lilac text-primary"
            ><BookOpen :size="20"
        /></span>
        <span class="min-w-0 flex-1"
            ><span class="block text-[11px] text-stone-500"
                >Shared {{ share.type.toLowerCase() }}</span
            ><span class="mt-1 block font-semibold [overflow-wrap:anywhere]">{{
                share.title
            }}</span
            ><span class="mt-1 block text-xs text-stone-500">{{
                share.date
            }}</span></span
        >
        <ArrowUpRight :size="17" class="shrink-0 text-primary" />
    </Link>
    <div
        v-else
        class="mt-2 flex items-center gap-3 rounded-2xl border bg-canvas p-4 text-sm text-stone-500"
        role="status"
    >
        <FileQuestion :size="21" />Post unavailable
    </div>
</template>
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, BookOpen, FileQuestion } from 'lucide-vue-next';
import type { SharedTimelinePost } from '@/types/messaging';
defineProps<{ share: SharedTimelinePost }>();
</script>
