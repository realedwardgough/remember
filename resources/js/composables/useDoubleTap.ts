import { onBeforeUnmount } from 'vue';

export type TapPosition = Pick<MouseEvent, 'clientX' | 'clientY' | 'timeStamp'>;

const doubleTapDelay = 400;
const movementTolerance = 12;

export function useDoubleTap(options: {
    enabled: () => boolean;
    onDoubleTap: (event: TapPosition) => void;
}) {
    let pending: { event: TapPosition; singleTap: () => void } | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let touchStart: TapPosition | null = null;
    let continuingTap = false;
    let lastTouchAt = -Infinity;

    const nearby = (
        first: Pick<TapPosition, 'clientX' | 'clientY'>,
        second: Pick<TapPosition, 'clientX' | 'clientY'>,
        tolerance = 40,
    ) =>
        Math.hypot(
            first.clientX - second.clientX,
            first.clientY - second.clientY,
        ) <= tolerance;

    const cancelTap = () => {
        clearTimeout(timer);
        pending = null;
        touchStart = null;
        continuingTap = false;
    };

    const queueTap = (
        event: TapPosition,
        singleTap: () => void,
        continued = false,
    ) => {
        if (pending) {
            const quick =
                continued ||
                event.timeStamp - pending.event.timeStamp <= doubleTapDelay;

            if (nearby(event, pending.event) && quick) {
                cancelTap();
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
        }, doubleTapDelay);
    };

    const handleTouchStart = (event: TouchEvent) => {
        if (!options.enabled()) {
            return;
        }

        lastTouchAt = Date.now();

        if (event.touches.length !== 1) {
            cancelTap();

            return;
        }

        const touch = event.touches[0];
        touchStart = {
            clientX: touch.clientX,
            clientY: touch.clientY,
            timeStamp: event.timeStamp,
        };
        continuingTap =
            !!pending &&
            nearby(touchStart, pending.event) &&
            event.timeStamp - pending.event.timeStamp <= doubleTapDelay;

        // Wait for the second finger lift instead of navigating mid-tap.
        if (continuingTap) {
            clearTimeout(timer);
        }
    };

    const handleTouchMove = (event: TouchEvent) => {
        if (!touchStart) {
            return;
        }

        const touch = event.touches[0];

        if (
            event.touches.length !== 1 ||
            !nearby(touchStart, touch, movementTolerance)
        ) {
            cancelTap();
        }
    };

    const handleTouchEnd = (event: TouchEvent, singleTap: () => void) => {
        if (!options.enabled()) {
            return;
        }

        lastTouchAt = Date.now();
        const start = touchStart;
        const continued = continuingTap;
        touchStart = null;
        continuingTap = false;
        const touch = event.changedTouches[0];

        if (
            !start ||
            !touch ||
            event.touches.length > 0 ||
            event.timeStamp - start.timeStamp > 500 ||
            !nearby(start, touch, movementTolerance)
        ) {
            cancelTap();

            return;
        }

        if (event.cancelable) {
            event.preventDefault();
        }

        queueTap(
            {
                clientX: touch.clientX,
                clientY: touch.clientY,
                timeStamp: event.timeStamp,
            },
            singleTap,
            continued,
        );
    };

    const handleTap = (event: MouseEvent, singleTap: () => void) => {
        // Touch browsers may synthesize a mouse click, sometimes with detail 0.
        // The touch handler already processed it; keyboard activation stays immediate.
        const keyboard =
            event.detail === 0 && event.clientX === 0 && event.clientY === 0;

        if (!keyboard && Date.now() - lastTouchAt < 800) {
            event.preventDefault();

            return;
        }

        if (
            !options.enabled() ||
            event.detail === 0 ||
            ('pointerType' in event && event.pointerType === 'mouse')
        ) {
            cancelTap();
            singleTap();

            return;
        }

        event.preventDefault();
        queueTap(event, singleTap);
    };

    onBeforeUnmount(cancelTap);

    return {
        handleTap,
        handleTouchStart,
        handleTouchMove,
        handleTouchEnd,
        cancelTap,
    };
}
