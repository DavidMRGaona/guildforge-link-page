import { computed, onScopeDispose, ref, watch, type ComputedRef } from 'vue';
import {
    nextChangeAt,
    partsUntil,
    phaseAt,
    tickDelay,
    type CountdownInstants,
    type CountdownParts,
    type FeaturedPhase,
} from '../utils/countdown';

/** What the server decided: its own «now» and the three instants that matter. */
export interface CountdownSource {
    serverNow: string | null;
    dayStartsAt: string | null;
    startsAt: string | null;
    endsAt: string | null;
}

export interface FeaturedCountdown {
    /** Null when the server sent no usable instants: the block then shows no countdown. */
    phase: ComputedRef<FeaturedPhase | null>;
    /** Time left until the start; null once the event has started or without instants. */
    parts: ComputedRef<CountdownParts | null>;
    /** Whether the last hour shows (and ticks) seconds. */
    showSeconds: ComputedRef<boolean>;
}

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

function parse(value: string | null): number {
    return value === null ? Number.NaN : Date.parse(value);
}

/**
 * The state of the featured event's countdown, counted from the server's instant: when the
 * props arrive, the gap between the server's «now» and the device clock is kept, and the device
 * clock only measures the time that passes after. A device with the wrong time or zone counts
 * the same as any other. Timers wake the page only when something visible changes: every second
 * in the last hour (never with reduced motion), else with the minute, and exactly at each
 * boundary (the venue day begins, the event starts, the event ends). `onEnded` runs once per
 * event, the moment it ends.
 */
export function useFeaturedCountdown(
    source: () => CountdownSource,
    onEnded: () => void
): FeaturedCountdown {
    const reduceMotion = ref(false);
    const now = ref(Date.now());
    let offset = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let endedFor: number | null = null;

    const instants = computed<CountdownInstants | null>(() => {
        const { serverNow, dayStartsAt, startsAt, endsAt } = source();
        const values = {
            serverNow: parse(serverNow),
            dayStartsAt: parse(dayStartsAt),
            startsAt: parse(startsAt),
            endsAt: parse(endsAt),
        };

        return Object.values(values).some(Number.isNaN)
            ? null
            : { dayStartsAt: values.dayStartsAt, startsAt: values.startsAt, endsAt: values.endsAt };
    });

    const phase = computed(() =>
        instants.value === null ? null : phaseAt(now.value, instants.value)
    );

    const parts = computed(() =>
        instants.value !== null && (phase.value === 'upcoming' || phase.value === 'today')
            ? partsUntil(instants.value.startsAt - now.value)
            : null
    );

    const showSeconds = computed(
        () =>
            !reduceMotion.value &&
            parts.value !== null &&
            parts.value.days === 0 &&
            parts.value.hours === 0
    );

    function schedule(): void {
        clearTimeout(timer);
        timer = undefined;

        const current = instants.value;
        if (current === null) {
            return;
        }

        const at = now.value;
        const change = nextChangeAt(at, current);
        if (change === null) {
            // Over: tell the page once, so it asks the server for the next event
            if (endedFor !== current.endsAt) {
                endedFor = current.endsAt;
                onEnded();
            }

            return;
        }

        const counting = at < current.startsAt;
        const delay = counting
            ? Math.min(tickDelay(current.startsAt - at, reduceMotion.value), change - at)
            : change - at;

        timer = setTimeout(tick, Math.max(delay, 0));
    }

    function tick(): void {
        now.value = Date.now() + offset;
        schedule();
    }

    function resync(): void {
        const serverMs = parse(source().serverNow);

        offset = Number.isNaN(serverMs) ? 0 : serverMs - Date.now();
        tick();
    }

    watch(
        () => {
            const { serverNow, dayStartsAt, startsAt, endsAt } = source();

            return [serverNow, dayStartsAt, startsAt, endsAt];
        },
        resync,
        { immediate: true }
    );

    // A hidden tab may have slept through its timers: look at the clock again on return
    const onVisible = (): void => {
        if (document.visibilityState === 'visible') {
            tick();
        }
    };
    document.addEventListener('visibilitychange', onVisible);

    let motionQuery: MediaQueryList | null = null;
    const onMotionChange = (event: MediaQueryListEvent): void => {
        reduceMotion.value = event.matches;
        schedule();
    };
    if (typeof window.matchMedia === 'function') {
        motionQuery = window.matchMedia(REDUCED_MOTION);
        reduceMotion.value = motionQuery.matches;
        motionQuery.addEventListener('change', onMotionChange);
        schedule();
    }

    onScopeDispose(() => {
        clearTimeout(timer);
        document.removeEventListener('visibilitychange', onVisible);
        motionQuery?.removeEventListener('change', onMotionChange);
    });

    return { phase, parts, showSeconds };
}
