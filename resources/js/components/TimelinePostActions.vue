<template>
    <DropdownMenuRoot v-if="post.canEdit">
        <DropdownMenuTrigger
            class="flex size-11 shrink-0 items-center justify-center rounded-full text-stone-500 hover:bg-lilac"
            :aria-label="`Actions for ${post.title}`"
            ><Ellipsis :size="20"
        /></DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent
                align="end"
                :side-offset="6"
                class="surface z-50 min-w-44 p-1.5"
            >
                <DropdownMenuItem
                    class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-3 text-sm data-highlighted:bg-lilac"
                    @select="emit('edit', post)"
                    ><Pencil :size="16" />Edit memory</DropdownMenuItem
                >
                <DropdownMenuItem
                    class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-3 text-sm text-rose-700 data-highlighted:bg-blush"
                    @select="showDelete = true"
                    ><Trash2 :size="16" />Delete memory</DropdownMenuItem
                >
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
    <ConfirmModal
        :show="showDelete"
        title="Delete this memory?"
        message="This memory and its photos, comments and reactions will be permanently removed."
        confirm-label="Delete memory"
        :processing="processing"
        @close="!processing && (showDelete = false)"
        @confirm="deletePost"
    />
</template>
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Ellipsis, Pencil, Trash2 } from 'lucide-vue-next';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';
import { ref } from 'vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import { destroy } from '@/routes/posts';
import type { TimelinePost } from '@/types';
const props = defineProps<{ post: TimelinePost }>();
const emit = defineEmits<{ edit: [post: TimelinePost] }>();
const showDelete = ref(false);
const processing = ref(false);
const deletePost = () => {
    if (processing.value) {
        return;
    }

    router.delete(destroy.url(props.post.id), {
        preserveScroll: true,
        onStart: () => {
            processing.value = true;
        },
        onSuccess: () => {
            showDelete.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>
