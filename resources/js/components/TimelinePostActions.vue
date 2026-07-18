<template>
    <div class="flex flex-col items-center">
        <button
            v-if="post.canEdit"
            type="button"
            class="inline-flex min-h-9 min-w-10 cursor-pointer items-center justify-center border-2 border-zinc-950 bg-white px-2.5 py-1.5 text-sm font-black text-zinc-700 shadow-[3px_3px_0_0_#18181b] transition-[background-color,box-shadow,translate] hover:-translate-x-0.5 hover:-translate-y-0.5 hover:bg-[#f8cc5d] focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-[#e06573] active:translate-x-0.5 active:translate-y-0.5 active:shadow-[1px_1px_0_0_#18181b]"
            :aria-label="'Edit ' + post.title"
            @click="emit('edit', post)"
        >
            <i class="fa-solid fa-pen text-xs"></i>
        </button>
    </div>
</template>

<script setup lang="ts">
import type { TimelinePost } from '@/types';

defineProps<{
    post: TimelinePost;
}>();

const emit = defineEmits<{
    edit: [post: TimelinePost];
}>();
</script>
