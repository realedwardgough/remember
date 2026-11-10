<template>
    <div
        ref="container"
        class="video-player group relative isolate overflow-hidden bg-black text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        :class="{
            'controls-visible':
                controlsVisible || !playing || buffering || error,
        }"
        role="region"
        :aria-label="`Video: ${name}`"
        tabindex="0"
        @keydown="handleKeydown"
        @pointermove="handlePointerMove"
        @pointerleave="scheduleHide"
        @focusin="showControls"
        @focusout="scheduleHide"
    >
        <video
            ref="element"
            :src="src"
            :poster="poster"
            :autoplay="autoplay"
            :aria-label="name"
            :controls="!enhanced"
            playsinline
            preload="metadata"
            class="mx-auto block w-full object-contain"
            :class="expanded ? 'max-h-[70dvh]' : 'max-h-96'"
            @pointerdown="lastPointerType = $event.pointerType"
            @click="handleVideoClick"
            @loadedmetadata="updateDuration"
            @durationchange="updateDuration"
            @timeupdate="updateTime"
            @play="handlePlay"
            @playing="buffering = false"
            @pause="handlePause"
            @ended="handlePause"
            @waiting="buffering = true"
            @canplay="buffering = false"
            @volumechange="rememberVolume"
            @error="handleError"
        />
        <template v-if="enhanced">
            <button
                v-if="!playing && !error"
                type="button"
                class="absolute top-1/2 left-1/2 flex size-16 -translate-1/2 items-center justify-center rounded-full bg-black/60 transition hover:bg-black/80 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white"
                :aria-label="ended ? 'Replay video' : 'Play video'"
                @click="togglePlayback"
            >
                <RotateCcw v-if="ended" :size="28" aria-hidden="true" />
                <Play
                    v-else
                    :size="28"
                    fill="currentColor"
                    aria-hidden="true"
                />
            </button>
            <div
                v-if="buffering && playing"
                class="pointer-events-none absolute inset-0 flex items-center justify-center"
                role="status"
            >
                <LoaderCircle
                    class="animate-spin motion-reduce:animate-none"
                    :size="32"
                    aria-hidden="true"
                />
                <span class="sr-only">Buffering video</span>
            </div>
            <div
                v-if="error"
                class="absolute inset-0 flex items-center justify-center bg-black/75 p-6 text-center text-sm"
                role="status"
            >
                {{ error }}
            </div>
            <div
                class="player-controls absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/60 to-transparent px-3 pt-10 pb-1 sm:px-4"
            >
                <input
                    class="block h-6 w-full cursor-pointer accent-white focus-visible:outline-2 focus-visible:outline-white disabled:cursor-default"
                    type="range"
                    min="0"
                    :max="duration || 0"
                    step="0.1"
                    :value="currentTime"
                    :disabled="!duration || !!error"
                    aria-label="Seek video"
                    :aria-valuetext="`${formatTime(currentTime)} of ${formatTime(duration)}`"
                    @input="seek"
                />
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="player-button"
                        :aria-label="
                            playing
                                ? 'Pause video'
                                : ended
                                  ? 'Replay video'
                                  : 'Play video'
                        "
                        :disabled="!!error"
                        @click="togglePlayback"
                    >
                        <Pause
                            v-if="playing"
                            :size="20"
                            fill="currentColor"
                            aria-hidden="true"
                        />
                        <Play
                            v-else
                            :size="20"
                            fill="currentColor"
                            aria-hidden="true"
                        />
                    </button>
                    <span
                        class="pointer-events-none mr-auto text-xs tabular-nums"
                        >{{ formatTime(currentTime) }} /
                        {{ formatTime(duration) }}</span
                    >
                    <button
                        type="button"
                        class="player-button"
                        :aria-label="
                            muted || volume === 0
                                ? 'Unmute video'
                                : 'Mute video'
                        "
                        @click="toggleMute"
                    >
                        <VolumeX
                            v-if="muted || volume === 0"
                            :size="20"
                            aria-hidden="true"
                        />
                        <Volume2 v-else :size="20" aria-hidden="true" />
                    </button>
                    <input
                        class="hidden h-6 w-16 cursor-pointer accent-white sm:block"
                        type="range"
                        min="0"
                        max="1"
                        step="0.05"
                        :value="muted ? 0 : volume"
                        aria-label="Volume"
                        @input="setVolume"
                    />
                    <button
                        v-if="fullscreenAvailable"
                        type="button"
                        class="player-button"
                        :aria-label="
                            fullscreen ? 'Exit fullscreen' : 'Enter fullscreen'
                        "
                        @click="toggleFullscreen"
                    >
                        <Minimize
                            v-if="fullscreen"
                            :size="20"
                            aria-hidden="true"
                        />
                        <Maximize v-else :size="20" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import {
    LoaderCircle,
    Maximize,
    Minimize,
    Pause,
    Play,
    RotateCcw,
    Volume2,
    VolumeX,
} from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{
    src: string;
    poster?: string;
    name: string;
    autoplay?: boolean;
    expanded?: boolean;
}>();

type InlineVideo = HTMLVideoElement & { webkitEnterFullscreen?: () => void };
const container = ref<HTMLDivElement | null>(null);
const element = ref<InlineVideo | null>(null);
const enhanced = ref(false);
const playing = ref(false);
const ended = ref(false);
const buffering = ref(false);
const error = ref('');
const currentTime = ref(0);
const duration = ref(0);
const volume = ref(1);
const muted = ref(false);
const controlsVisible = ref(true);
const fullscreen = ref(false);
const fullscreenAvailable = ref(false);
const lastPointerType = ref('');
let hideTimer: ReturnType<typeof setTimeout> | undefined;

const formatTime = (seconds: number) => {
    const total = Math.max(0, Math.floor(seconds));
    const minutes = Math.floor(total / 60);

    return `${minutes}:${String(total % 60).padStart(2, '0')}`;
};
const scheduleHide = () => {
    clearTimeout(hideTimer);

    if (playing.value) {
        hideTimer = setTimeout(() => {
            controlsVisible.value = false;
        }, 2500);
    }
};
const showControls = () => {
    controlsVisible.value = true;
    scheduleHide();
};
const handlePointerMove = (event: PointerEvent) => {
    if (event.pointerType === 'mouse') {
        showControls();
    }
};
const updateDuration = () => {
    const value = element.value?.duration ?? 0;
    duration.value = Number.isFinite(value) ? value : 0;
};
const updateTime = () => {
    currentTime.value = element.value?.currentTime ?? 0;
};
const handlePlay = () => {
    document.querySelectorAll('video').forEach((video) => {
        if (video !== element.value && !video.paused) {
            video.pause();
        }
    });
    playing.value = true;
    ended.value = false;
    error.value = '';
    showControls();
};
const handlePause = () => {
    playing.value = false;
    ended.value = element.value?.ended ?? false;
    buffering.value = false;
    showControls();
};
const handleError = () => {
    error.value =
        'This video could not be played. Try reopening it or download it from the video gallery.';
    buffering.value = false;
    playing.value = false;
};
const togglePlayback = async () => {
    const video = element.value;

    if (!video || error.value) {
        return;
    }

    if (video.paused) {
        try {
            await video.play();
        } catch {
            showControls();
        }
    } else {
        video.pause();
    }
};
const handleVideoClick = (event: MouseEvent) => {
    if (!enhanced.value) {
        return;
    }

    // The first touch reveals hidden controls without interrupting playback.
    if (
        ((event as PointerEvent).pointerType || lastPointerType.value) ===
            'touch' &&
        !controlsVisible.value
    ) {
        showControls();

        return;
    }

    void togglePlayback();
    showControls();
};
const seek = (event: Event) => {
    if (element.value) {
        element.value.currentTime = Number(
            (event.target as HTMLInputElement).value,
        );
    }

    updateTime();
    showControls();
};
const rememberVolume = () => {
    if (!element.value) {
        return;
    }

    volume.value = element.value.volume;
    muted.value = element.value.muted;

    try {
        localStorage.setItem(
            'remember-video-volume',
            JSON.stringify({ volume: volume.value, muted: muted.value }),
        );
    } catch {
        /* Playback works when storage is unavailable. */
    }
};
const toggleMute = () => {
    if (!element.value) {
        return;
    }

    const wasSilent = element.value.muted || element.value.volume === 0;

    if (element.value.volume === 0) {
        element.value.volume = 1;
    }

    element.value.muted = !wasSilent;
    showControls();
};
const setVolume = (event: Event) => {
    if (!element.value) {
        return;
    }

    element.value.volume = Number((event.target as HTMLInputElement).value);
    element.value.muted = element.value.volume === 0;
    showControls();
};
const updateFullscreen = () => {
    fullscreen.value = document.fullscreenElement === container.value;
};
const toggleFullscreen = async () => {
    try {
        if (document.fullscreenElement === container.value) {
            await document.exitFullscreen();
        } else if (
            container.value?.requestFullscreen &&
            document.fullscreenEnabled
        ) {
            await container.value.requestFullscreen();
        } else {
            element.value?.webkitEnterFullscreen?.();
        }
    } catch {
        showControls();
    }
};
const handleKeydown = (event: KeyboardEvent) => {
    // Leave buttons and sliders to their native keyboard interactions.
    if (event.target !== container.value && event.target !== element.value) {
        return;
    }

    if (event.key === ' ' || event.key === 'k') {
        event.preventDefault();
        void togglePlayback();
    } else if (
        ['ArrowLeft', 'ArrowRight'].includes(event.key) &&
        element.value &&
        duration.value
    ) {
        event.preventDefault();
        element.value.currentTime = Math.min(
            duration.value,
            Math.max(
                0,
                element.value.currentTime +
                    (event.key === 'ArrowLeft' ? -5 : 5),
            ),
        );
        updateTime();
    } else if (event.key === 'm') {
        toggleMute();
    } else if (event.key === 'f') {
        event.preventDefault();
        void toggleFullscreen();
    }

    showControls();
};

onMounted(() => {
    const video = element.value;

    if (!video) {
        return;
    }

    try {
        const saved = JSON.parse(
            localStorage.getItem('remember-video-volume') ?? 'null',
        );

        if (
            saved &&
            typeof saved.volume === 'number' &&
            Number.isFinite(saved.volume)
        ) {
            video.volume = Math.min(1, Math.max(0, saved.volume));
            video.muted = saved.muted === true;
        }
    } catch {
        /* Keep the browser's volume defaults. */
    }

    volume.value = video.volume;
    muted.value = video.muted;
    playing.value = !video.paused;
    updateDuration();
    updateTime();
    fullscreenAvailable.value = !!(
        document.fullscreenEnabled || video.webkitEnterFullscreen
    );
    document.addEventListener('fullscreenchange', updateFullscreen);
    enhanced.value = true;

    if (playing.value) {
        handlePlay();
    }
});
onBeforeUnmount(() => {
    clearTimeout(hideTimer);
    document.removeEventListener('fullscreenchange', updateFullscreen);
    element.value?.pause();
});
</script>

<style scoped>
.player-controls {
    transition: opacity 180ms;
}
.video-player:not(.controls-visible):not(:has(:focus-visible))
    .player-controls {
    pointer-events: none;
    opacity: 0;
}
.player-button {
    display: flex;
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    cursor: pointer;
}
.player-button:hover {
    background: rgb(255 255 255 / 15%);
}
.player-button:focus-visible {
    outline: 2px solid white;
    outline-offset: -2px;
}
.player-button:disabled {
    cursor: default;
    opacity: 0.5;
}
.video-player:fullscreen {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}
.video-player:fullscreen video {
    max-height: 100dvh;
    height: 100%;
}
@media (prefers-reduced-motion: reduce) {
    .player-controls {
        transition: none;
    }
}
</style>
