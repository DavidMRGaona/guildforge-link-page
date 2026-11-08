/**
 * The arithmetic of the featured event's countdown. Every argument is an instant in
 * milliseconds; the server decides which instants matter (when the venue day of the event began,
 * when it starts and when it ends), so nothing here knows about time zones or the visitor's
 * calendar.
 */

const SECOND = 1000;
const MINUTE = 60 * SECOND;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

/** Slack after a boundary, so the tick lands on the new value and not just before it. */
const TICK_SLACK = 20;

/** upcoming: days away; today: the event's day, not started; live: in progress; ended: over. */
export type FeaturedPhase = 'upcoming' | 'today' | 'live' | 'ended';

export interface CountdownInstants {
    /** The first instant of the venue day the event starts on. */
    dayStartsAt: number;
    startsAt: number;
    endsAt: number;
}

export interface CountdownParts {
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
}

export function phaseAt(now: number, instants: CountdownInstants): FeaturedPhase {
    if (now >= instants.endsAt) {
        return 'ended';
    }

    if (now >= instants.startsAt) {
        return 'live';
    }

    return now >= instants.dayStartsAt ? 'today' : 'upcoming';
}

/** The next instant after `now` at which the phase changes; null when the event is over. */
export function nextChangeAt(now: number, instants: CountdownInstants): number | null {
    for (const boundary of [instants.dayStartsAt, instants.startsAt, instants.endsAt]) {
        if (boundary > now) {
            return boundary;
        }
    }

    return null;
}

/** Whole days, hours, minutes and seconds left (rounded down, never negative). */
export function partsUntil(remainingMs: number): CountdownParts {
    const left = Math.max(0, remainingMs);

    return {
        days: Math.floor(left / DAY),
        hours: Math.floor((left % DAY) / HOUR),
        minutes: Math.floor((left % HOUR) / MINUTE),
        seconds: Math.floor((left % MINUTE) / SECOND),
    };
}

/**
 * How long to wait before the shown value changes. Seconds tick only in the last hour and never
 * with reduced motion; otherwise the value changes with the minute.
 */
export function tickDelay(remainingMs: number, reduceMotion: boolean): number {
    if (remainingMs < HOUR && !reduceMotion) {
        const untilNextSecond = remainingMs % SECOND;

        return untilNextSecond === 0 ? SECOND : untilNextSecond + TICK_SLACK;
    }

    const untilNextMinute = remainingMs % MINUTE;

    return untilNextMinute === 0 ? MINUTE : untilNextMinute + TICK_SLACK;
}
