<template>
    <div
        ref="container"
        class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
    >
        <button
            v-if="
                preview ||
                (clickToPlay &&
                    !activated &&
                    (video.status === 'ready' || !video.status))
            "
            type="button"
            class="relative block w-full overflow-hidden bg-slate-100 text-primary focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-primary"
            :class="preview ? 'aspect-square' : 'aspect-video'"
            :aria-label="`Open video: ${video.name}`"
            @click.stop="openVideo"
        >
            <img
                v-if="video.thumbnailUrl"
                :src="video.thumbnailUrl"
                :alt="video.name"
                loading="lazy"
                class="size-full object-cover"
            />
            <Video v-else :size="48" class="mx-auto" aria-hidden="true" />
            <span
                class="absolute inset-0 flex items-center justify-center bg-black/10"
            >
                <span
                    class="flex size-12 items-center justify-center rounded-full bg-black/60 text-white"
                >
                    <Play :size="24" fill="currentColor" aria-hidden="true" />
                </span>
            </span>
            <span
                v-if="video.status && video.status !== 'ready'"
                class="absolute inset-x-0 bottom-0 bg-black/70 p-2 text-xs text-white"
                >{{
                    video.status === 'failed'
                        ? 'Processing failed'
                        : 'Preparing video…'
                }}</span
            >
        </button>
        <VideoPlayer
            v-else-if="video.status === 'ready' || !video.status"
            :src="video.url"
            :poster="video.thumbnailUrl ?? undefined"
            :name="video.name"
            :autoplay="autoplay || activated"
            :expanded="expanded"
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
import { Play, Video } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import VideoPlayer from '@/components/VideoPlayer.vue';
import type { TimelineMedia } from '@/types';

const props = defineProps<{
    media: TimelineMedia;
    preview?: boolean;
    clickToPlay?: boolean;
    autoplay?: boolean;
    expanded?: boolean;
}>();
const emit = defineEmits<{
    updated: [media: TimelineMedia];
    open: [media: TimelineMedia];
}>();
const video = ref(props.media);
const activated = ref(false);
const statusUnavailable = ref(false);
const container = ref<HTMLElement | null>(null);
const inViewport = ref(false);
let observer: IntersectionObserver | undefined;
let inFlight = false;

const openVideo = () => {
    if (props.preview) {
        emit('open', video.value);
    } else {
        activated.value = true;
    }
};
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;
let stopped = false;

const active = () =>
    !stopped && inViewport.value && document.visibilityState === 'visible';
const schedule = (delay = 5000) => {
    clearTimeout(timer);

    if (active() && !inFlight) {
        timer = setTimeout(poll, delay);
    }
};
const pause = () => {
    clearTimeout(timer);
    controller?.abort();
};
const visibilityChanged = () => {
    if (active()) {
        schedule(0);
    } else {
        pause();
    }
};
const poll = async () => {
    if (
        !active() ||
        inFlight ||
        !video.value.statusUrl ||
        (!['pending', 'processing'].includes(video.value.status ?? '') &&
            (video.value.thumbnailUrl ||
                !['pending', 'processing'].includes(
                    video.value.thumbnailStatus ?? '',
                )))
    ) {
        return;
    }

    inFlight = true;
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

        const updated: TimelineMedia = await response.json();

        if (controller.signal.aborted || stopped) {
            return;
        }

        video.value = updated;
        emit('updated', video.value);
        statusUnavailable.value = false;
    } catch {
        if (!controller.signal.aborted) {
            statusUnavailable.value = true;
        }
    } finally {
        inFlight = false;
        schedule();
    }
};

watch(
    () => props.media,
    (media) => {
        if (media.id !== video.value.id) {
            activated.value = false;
            controller?.abort();
            stopped = false;
        }

        video.value = media;
        schedule();
    },
    { immediate: true },
);
onMounted(() => {
    document.addEventListener('visibilitychange', visibilityChanged);

    if (typeof IntersectionObserver === 'undefined') {
        inViewport.value = true;
        schedule();
    } else {
        observer = new IntersectionObserver((entries) => {
            inViewport.value = entries.some((entry) => entry.isIntersecting);
            visibilityChanged();
        });

        if (container.value) {
            observer.observe(container.value);
        }
    }
});
onBeforeUnmount(() => {
    stopped = true;
    pause();
    observer?.disconnect();
    document.removeEventListener('visibilitychange', visibilityChanged);
});
</script>
