import { onBeforeUnmount } from 'vue';

export function useDoubleTap(options: {
    enabled: () => boolean;
    onDoubleTap: (event: MouseEvent) => void;
}) {
    let pending: { event: MouseEvent; singleTap: () => void } | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const cancelTap = () => {
        clearTimeout(timer);
        pending = null;
    };

    const handleTap = (event: MouseEvent, singleTap: () => void) => {
        if (
            !options.enabled() ||
            event.detail === 0 ||
            ('pointerType' in event && event.pointerType === 'mouse')
        ) {
            cancelTap();
            singleTap();

            return;
        }

        if (pending) {
            const nearby =
                Math.hypot(
                    event.clientX - pending.event.clientX,
                    event.clientY - pending.event.clientY,
                ) <= 40;
            const quick = event.timeStamp - pending.event.timeStamp <= 300;

            if (nearby && quick) {
                cancelTap();
                event.preventDefault();
                options.onDoubleTap(event);

                return;
            }

            const previousTap = pending.singleTap;
            cancelTap();
            previousTap();
        }

        pending = { event, singleTap };
        timer = setTimeout(() => {
            pending = null;

            if (options.enabled()) {
                singleTap();
            }
        }, 300);
    };

    onBeforeUnmount(cancelTap);

    return { handleTap, cancelTap };
}
