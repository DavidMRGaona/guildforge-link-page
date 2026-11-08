import { describe, expect, it } from 'vitest';
import {
    nextChangeAt,
    partsUntil,
    phaseAt,
    tickDelay,
    type CountdownInstants,
} from '../../resources/js/utils/countdown';

const SECOND = 1000;
const MINUTE = 60 * SECOND;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

// 14 Nov 2026, event 18:00 to 22:00 in Madrid (UTC+1); its day began at midnight Madrid
const instants: CountdownInstants = {
    dayStartsAt: Date.parse('2026-11-14T00:00:00+01:00'),
    startsAt: Date.parse('2026-11-14T18:00:00+01:00'),
    endsAt: Date.parse('2026-11-14T22:00:00+01:00'),
};

describe('phaseAt', () => {
    it('counts down until the venue day of the event begins', () => {
        expect(phaseAt(instants.dayStartsAt - 1, instants)).toBe('upcoming');
    });

    it('says today from the first instant of the venue day, before the event starts', () => {
        expect(phaseAt(instants.dayStartsAt, instants)).toBe('today');
        expect(phaseAt(instants.startsAt - 1, instants)).toBe('today');
    });

    it('says live from the exact start until just before the end', () => {
        expect(phaseAt(instants.startsAt, instants)).toBe('live');
        expect(phaseAt(instants.endsAt - 1, instants)).toBe('live');
    });

    it('is over at the exact end', () => {
        expect(phaseAt(instants.endsAt, instants)).toBe('ended');
        expect(phaseAt(instants.endsAt + DAY, instants)).toBe('ended');
    });

    it('goes straight to live for an event that started on an earlier day', () => {
        const longRunning = { ...instants, dayStartsAt: instants.dayStartsAt - 2 * DAY, startsAt: instants.startsAt - 2 * DAY };

        expect(phaseAt(instants.startsAt - DAY, longRunning)).toBe('live');
    });
});

describe('nextChangeAt', () => {
    it('is the next boundary the countdown crosses', () => {
        expect(nextChangeAt(instants.dayStartsAt - 5, instants)).toBe(instants.dayStartsAt);
        expect(nextChangeAt(instants.dayStartsAt, instants)).toBe(instants.startsAt);
        expect(nextChangeAt(instants.startsAt, instants)).toBe(instants.endsAt);
    });

    it('is null once the event is over', () => {
        expect(nextChangeAt(instants.endsAt, instants)).toBeNull();
    });
});

describe('partsUntil', () => {
    it('splits the time left into whole days, hours, minutes and seconds', () => {
        const left = 3 * DAY + 4 * HOUR + 5 * MINUTE + 6 * SECOND + 999;

        expect(partsUntil(left)).toEqual({ days: 3, hours: 4, minutes: 5, seconds: 6 });
    });

    it('never goes below zero', () => {
        expect(partsUntil(-5000)).toEqual({ days: 0, hours: 0, minutes: 0, seconds: 0 });
    });
});

describe('tickDelay', () => {
    it('ticks every second in the last hour', () => {
        expect(tickDelay(30 * MINUTE + 250, false)).toBe(250 + 20);
    });

    it('waits for the next minute change with more than an hour left', () => {
        expect(tickDelay(2 * HOUR + 10 * SECOND, false)).toBe(10 * SECOND + 20);
    });

    it('never ticks per second with reduced motion', () => {
        expect(tickDelay(30 * MINUTE + 10 * SECOND, true)).toBe(10 * SECOND + 20);
    });

    it('waits a whole minute when the minute has just changed', () => {
        expect(tickDelay(2 * HOUR, false)).toBe(MINUTE);
    });
});
