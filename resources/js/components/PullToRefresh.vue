<template>
    <div ref="surface" :aria-busy="refreshing">
        <div
            class="overflow-hidden transition-[height] duration-200 lg:hidden"
            :class="{ 'transition-none': pulling }"
            :style="{ height: `${indicatorHeight}px` }"
        >
            <div
                class="flex h-full min-h-12 items-center justify-center gap-2 text-xs font-medium text-primary"
                role="status"
                aria-live="polite"
                aria-atomic="true"
            >
                <LoaderCircle
                    v-if="refreshing"
                    :size="18"
                    class="animate-spin"
                    aria-hidden="true"
                />
                <ArrowDown
                    v-else-if="pulling"
                    :size="18"
                    class="transition-transform"
                    :class="{ 'rotate-180': ready }"
                    aria-hidden="true"
                />
                <span>{{ label }}</span>
            </div>
        </div>
        <slot />
    </div>
</template>

<script setup lang="ts">
import { useEventListener, useMediaQuery } from '@vueuse/core';
import { ArrowDown, LoaderCircle } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    refresh: () => Promise<void>;
    disabled?: boolean;
}>();

const surface = ref<HTMLElement | null>(null);
const isMobile = useMediaQuery('(max-width: 1023px)');
const enabled = computed(() => isMobile.value && !props.disabled);
const distance = ref(0);
const refreshing = ref(false);
const message = ref('');
const pulling = computed(() => distance.value > 0 && !refreshing.value);
const ready = computed(() => distance.value >= 64);
const indicatorHeight = computed(() =>
    !isMobile.value
        ? 0
        : refreshing.value || message.value
          ? 48
          : distance.value,
);
const label = computed(() =>
    refreshing.value
        ? 'Checking for updates…'
        : message.value ||
          (pulling.value
              ? ready.value
                  ? 'Release to refresh'
                  : 'Pull to refresh'
              : ''),
);

let disposed = false;
let start: { x: number; y: number } | null = null;
let feedbackTimer: ReturnType<typeof setTimeout> | undefined;
const resetGesture = () => {
    start = null;
    distance.value = 0;
};

// Disable browser refresh while this mobile timeline handles the gesture.
watch(
    enabled,
    (active, _, onCleanup) => {
        resetGesture();

        if (!active || typeof document === 'undefined') {
            return;
        }

        const style = document.documentElement.style;
        const previous = style.overscrollBehaviorY;
        style.overscrollBehaviorY = 'none';
        onCleanup(() => {
            style.overscrollBehaviorY = previous;
        });
    },
    { immediate: true },
);

useEventListener(
    surface,
    'touchstart',
    (event: TouchEvent) => {
        resetGesture();

        if (
            !enabled.value ||
            refreshing.value ||
            window.scrollY > 0 ||
            event.touches.length !== 1
        ) {
            return;
        }

        const target = event.target as HTMLElement | null;

        if (
            target?.closest('input, textarea, select, [contenteditable="true"]')
        ) {
            return;
        }

        // Leave photo carousels, filter strips, and other scrollable areas alone.
        for (
            let element = target;
            element && element !== surface.value;
            element = element.parentElement
        ) {
            const style = getComputedStyle(element);

            if (
                (/(auto|scroll)/.test(style.overflowX) &&
                    element.scrollWidth > element.clientWidth) ||
                (/(auto|scroll)/.test(style.overflowY) &&
                    element.scrollHeight > element.clientHeight)
            ) {
                return;
            }
        }

        const touch = event.touches[0];
        start = { x: touch.clientX, y: touch.clientY };
    },
    { passive: true },
);

useEventListener(
    surface,
    'touchmove',
    (event: TouchEvent) => {
        if (!start) {
            return;
        }

        if (
            !enabled.value ||
            event.touches.length !== 1 ||
            window.scrollY > 0
        ) {
            resetGesture();

            return;
        }

        const touch = event.touches[0];
        const deltaX = Math.abs(touch.clientX - start.x);
        const deltaY = touch.clientY - start.y;

        if (deltaY < 0 || (deltaX > 8 && deltaX > deltaY)) {
            resetGesture();

            return;
        }

        if (deltaY <= 0 || !event.cancelable) {
            return;
        }

        event.preventDefault();
        clearTimeout(feedbackTimer);
        message.value = '';
        distance.value = Math.min(deltaY * 0.5, 100);
    },
    { passive: false },
);

useEventListener(
    surface,
    'touchend',
    async () => {
        const shouldRefresh = enabled.value && ready.value && !refreshing.value;
        resetGesture();

        if (!shouldRefresh) {
            return;
        }

        refreshing.value = true;

        try {
            await props.refresh();
            message.value = 'Timeline updated';
        } catch {
            message.value = 'Couldn’t refresh. Pull down to try again.';
        } finally {
            refreshing.value = false;

            if (!disposed) {
                feedbackTimer = setTimeout(() => {
                    message.value = '';
                }, 2500);
            }
        }
    },
    { passive: true },
);

useEventListener(surface, 'touchcancel', resetGesture, { passive: true });
onBeforeUnmount(() => {
    disposed = true;
    clearTimeout(feedbackTimer);
});
</script>
