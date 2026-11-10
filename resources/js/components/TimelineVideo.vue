<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
        <video
            v-if="video.status === 'ready' || !video.status"
            :src="video.url"
            :aria-label="video.name"
            controls
            playsinline
            preload="metadata"
            class="max-h-96 w-full bg-black"
        />
        <div v-else class="p-4 text-sm text-slate-600" role="status">
            <p class="font-medium">{{ video.name }}</p>
            <p v-if="video.status === 'failed'" class="mt-1">
                This video could not be processed. The original is safe.
                <a :href="video.downloadUrl" class="underline"
                    >Download original</a
                >
            </p>
            <p v-else class="mt-1">
                Preparing your video… You can keep browsing.
            </p>
            <p v-if="statusUnavailable" class="mt-1">
                Status is temporarily unavailable. Checking again shortly.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import type { TimelineMedia } from '@/types';

const props = defineProps<{ media: TimelineMedia }>();
const video = ref(props.media);
const statusUnavailable = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;
let stopped = false;

const poll = async () => {
    if (
        stopped ||
        !video.value.statusUrl ||
        !['pending', 'processing'].includes(video.value.status ?? '')
    ) {
        return;
    }

    controller = new AbortController();

    try {
        const response = await fetch(video.value.statusUrl, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if ([401, 403, 404].includes(response.status)) {
            stopped = true;

            return;
        }

        if (!response.ok) {
            throw new Error('Status unavailable');
        }

        video.value = await response.json();
        statusUnavailable.value = false;
    } catch {
        statusUnavailable.value = true;
    }

    if (!stopped) {
        timer = setTimeout(poll, 5000);
    }
};

watch(
    () => props.media,
    (media) => {
        video.value = media;
        clearTimeout(timer);
        timer = setTimeout(poll, 5000);
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    stopped = true;
    clearTimeout(timer);
    controller?.abort();
});
</script>
