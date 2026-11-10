import { useMediaQuery } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

export function useMessagesViewport() {
    const mobile = useMediaQuery('(max-width: 1023px)');
    const height = ref<number | null>(null);
    const offset = ref(0);
    let frame: number | undefined;

    watch(
        mobile,
        (active, _, onCleanup) => {
            if (!active || typeof document === 'undefined') {
                return;
            }

            document.documentElement.classList.add('messages-mobile-shell');
            onCleanup(() =>
                document.documentElement.classList.remove(
                    'messages-mobile-shell',
                ),
            );
        },
        { immediate: true },
    );

    function updateViewport() {
        const viewport = window.visualViewport;
        const editor = document.activeElement;
        const editing =
            editor instanceof HTMLElement &&
            editor.matches('input, textarea, [contenteditable="true"]') &&
            editor.closest('.messages-workspace');

        // Elastic scrolling and browser toolbar movement are not keyboard movement.
        // Leave the inbox and navigation anchored to CSS's viewport in those cases.
        height.value =
            mobile.value && editing && viewport?.scale === 1
                ? viewport.height
                : null;
        offset.value =
            height.value !== null ? Math.max(0, viewport?.offsetTop ?? 0) : 0;
    }

    function scheduleUpdate() {
        if (frame !== undefined) {
            cancelAnimationFrame(frame);
        }

        frame = requestAnimationFrame(updateViewport);
    }

    onMounted(() => {
        window.visualViewport?.addEventListener('resize', scheduleUpdate);
        window.visualViewport?.addEventListener('scroll', scheduleUpdate);
        document.addEventListener('focusin', scheduleUpdate);
        document.addEventListener('focusout', scheduleUpdate);
    });
    onBeforeUnmount(() => {
        if (frame !== undefined) {
            cancelAnimationFrame(frame);
        }

        window.visualViewport?.removeEventListener('resize', scheduleUpdate);
        window.visualViewport?.removeEventListener('scroll', scheduleUpdate);
        document.removeEventListener('focusin', scheduleUpdate);
        document.removeEventListener('focusout', scheduleUpdate);
    });

    return {
        workspaceStyle: computed(() =>
            height.value === null
                ? {}
                : {
                      '--messages-viewport': `${height.value}px`,
                      '--messages-offset': `${offset.value}px`,
                  },
        ),
        mobileNavigationStyle: computed(() =>
            height.value === null
                ? undefined
                : {
                      top: `calc(${offset.value}px + ${height.value}px - 4.5rem - 1px - env(safe-area-inset-bottom))`,
                      bottom: 'auto',
                  },
        ),
    };
}
