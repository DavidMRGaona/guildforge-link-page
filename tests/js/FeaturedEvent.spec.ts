import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import FeaturedEvent from '../../resources/js/components/FeaturedEvent.vue';
import es from '../../resources/js/locales/es';
import type { LinkPageFeatured } from '../../resources/js/types/linkPage';

vi.mock('@/utils/cloudinary', () => ({
    buildCardImageUrl: (id: string | null | undefined): string | null =>
        id ? `https://images.test/${id}.jpg` : null,
}));

// 14 Nov 2026 in Madrid (UTC+1), 18:00 to 22:00; its venue day began at midnight
const featured: LinkPageFeatured = {
    id: 'event-1',
    title: 'Jornadas de rol',
    url: 'https://example.test/eventos/jornadas-de-rol',
    startsAt: '2026-11-14T18:00:00+01:00',
    endsAt: '2026-11-14T22:00:00+01:00',
    dayStartsAt: '2026-11-14T00:00:00+01:00',
    spansDays: false,
    location: 'Sala del club',
    imagePublicId: null,
};

// The device clock is deliberately far off: the countdown must count from the server's instant
const DEVICE_NOW = '2001-01-01T00:00:00Z';

function mountBlock(
    serverNow: string,
    overrides: Partial<LinkPageFeatured> = {}
): ReturnType<typeof mount<typeof FeaturedEvent>> {
    vi.setSystemTime(new Date(DEVICE_NOW));

    return mount(FeaturedEvent, {
        props: { featured: { ...featured, ...overrides }, serverNow },
        attachTo: document.body,
    });
}

function countdown(wrapper: ReturnType<typeof mountBlock>): string {
    return wrapper.find('[data-testid="link-page-featured-countdown-value"]').text();
}

function label(wrapper: ReturnType<typeof mountBlock>): string {
    return wrapper.get('[data-testid="link-page-featured-label"]').text();
}

function setReducedMotion(reduce: boolean): void {
    vi.stubGlobal(
        'matchMedia',
        vi.fn().mockImplementation((query: string) => ({
            matches: reduce && query.includes('prefers-reduced-motion'),
            media: query,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }))
    );
}

describe('FeaturedEvent', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        setReducedMotion(false);
        vi.mocked(router.reload).mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('shows the event with its title, date, place and a link to it', () => {
        const wrapper = mountBlock('2026-11-13T10:00:00+01:00');

        expect(wrapper.get('h2').text()).toContain('Jornadas de rol');
        const time = wrapper.get('time');
        expect(time.attributes('datetime')).toBe('2026-11-14T18:00:00+01:00');
        expect(time.text()).toContain('18:00');
        expect(wrapper.text()).toContain('Sala del club');

        const link = wrapper.get('[data-testid="link-page-featured-link"]');
        expect(link.attributes('href')).toBe(featured.url);
        // The link says which event it opens, for screen readers
        expect(link.text()).toContain('Jornadas de rol');
    });

    it('counts down from the server instant, not from the device clock', () => {
        const wrapper = mountBlock('2026-11-13T10:00:00+01:00');

        expect(countdown(wrapper)).toBe('1 d 8 h');
    });

    it('keeps counting as time passes', async () => {
        const wrapper = mountBlock('2026-11-13T10:00:00+01:00');

        await vi.advanceTimersByTimeAsync(3 * 60 * 60 * 1000);

        expect(countdown(wrapper)).toBe('1 d 5 h');
    });

    it('hides the ticking numbers from screen readers, which get the date as text', () => {
        const wrapper = mountBlock('2026-11-13T10:00:00+01:00');

        expect(wrapper.get('[data-testid="link-page-featured-countdown"]').attributes('aria-hidden')).toBe('true');
        expect(wrapper.get('time').attributes('aria-hidden')).toBeUndefined();
    });

    it('has a polite status region from the start, empty until the event is today', () => {
        const wrapper = mountBlock('2026-11-13T10:00:00+01:00');

        const status = wrapper.get('[data-testid="link-page-featured-label"]');
        expect(status.attributes('role')).toBe('status');
        expect(status.text()).toBe('');
    });

    it('says «Hoy» from the first instant of the venue day, still counting down', async () => {
        const wrapper = mountBlock('2026-11-13T23:59:59+01:00');
        expect(label(wrapper)).toBe('');

        await vi.advanceTimersByTimeAsync(1000);

        expect(label(wrapper)).toBe(es.linkPage.featuredToday);
        expect(countdown(wrapper)).toBe('18 h 0 min');
    });

    it('says «Ahora mismo» on the second the event starts and drops the countdown', async () => {
        const wrapper = mountBlock('2026-11-14T17:59:59+01:00');
        expect(label(wrapper)).toBe(es.linkPage.featuredToday);

        await vi.advanceTimersByTimeAsync(1000);

        expect(label(wrapper)).toBe(es.linkPage.featuredLive);
        expect(wrapper.find('[data-testid="link-page-featured-countdown"]').exists()).toBe(false);
    });

    it('asks the server for what comes next on the second the event ends, once', async () => {
        const wrapper = mountBlock('2026-11-14T21:59:59+01:00');
        expect(label(wrapper)).toBe(es.linkPage.featuredLive);
        expect(router.reload).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1000);
        await vi.advanceTimersByTimeAsync(5 * 60 * 1000);

        expect(router.reload).toHaveBeenCalledTimes(1);
        expect(router.reload).toHaveBeenCalledWith({ only: ['featured', 'events', 'serverNow'] });
        expect(wrapper.exists()).toBe(true);
    });

    it('asks again when the next event also ends', async () => {
        const wrapper = mountBlock('2026-11-14T21:59:59+01:00');
        await vi.advanceTimersByTimeAsync(1000);
        expect(router.reload).toHaveBeenCalledTimes(1);

        await wrapper.setProps({
            featured: {
                ...featured,
                id: 'event-2',
                startsAt: '2026-11-15T10:00:00+01:00',
                endsAt: '2026-11-15T11:00:00+01:00',
                dayStartsAt: '2026-11-15T00:00:00+01:00',
            },
            serverNow: '2026-11-15T10:59:59+01:00',
        });
        await vi.advanceTimersByTimeAsync(1000);

        expect(router.reload).toHaveBeenCalledTimes(2);
    });

    it('ticks every second in the last hour', async () => {
        const wrapper = mountBlock('2026-11-14T17:30:30+01:00');
        expect(countdown(wrapper)).toBe('29 min 30 s');

        await vi.advanceTimersByTimeAsync(3000);

        expect(countdown(wrapper)).toBe('29 min 27 s');
    });

    it('never shows or ticks seconds with reduced motion', async () => {
        setReducedMotion(true);
        const wrapper = mountBlock('2026-11-14T17:30:30+01:00');

        expect(countdown(wrapper)).toBe('29 min');

        await vi.advanceTimersByTimeAsync(31 * 1000);

        expect(countdown(wrapper)).toBe('28 min');
    });

    it('says less than a minute instead of counting seconds with reduced motion', () => {
        setReducedMotion(true);
        const wrapper = mountBlock('2026-11-14T17:59:30+01:00');

        expect(countdown(wrapper)).toBe(es.linkPage.countdownLessThanMinute);
    });

    it('shows the days it spans and, once live, when it ends', async () => {
        const wrapper = mountBlock('2026-11-10T10:00:00+01:00', {
            startsAt: '2026-11-14T18:00:00+01:00',
            endsAt: '2026-11-16T20:00:00+01:00',
            spansDays: true,
        });

        expect(wrapper.get('time').text()).toMatch(/14\s?[–-]\s?16/);

        await wrapper.setProps({ serverNow: '2026-11-15T12:00:00+01:00' });
        await vi.advanceTimersByTimeAsync(1);

        expect(label(wrapper)).toBe(es.linkPage.featuredLive);
        expect(wrapper.text()).toContain('16');
        expect(wrapper.get('[data-testid="link-page-featured-until"]').text()).toContain('lun');
    });

    it('shows the image of the event, decorative, when it has one', () => {
        const wrapper = mountBlock('2026-11-13T10:00:00+01:00', { imagePublicId: 'events/cover' });

        const image = wrapper.get('img');
        expect(image.attributes('src')).toBe('https://images.test/events/cover.jpg');
        expect(image.attributes('alt')).toBe('');
    });

    it('has no image without one', () => {
        const wrapper = mountBlock('2026-11-13T10:00:00+01:00');

        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('still shows the event without a countdown when the server sent no usable instants', () => {
        const wrapper = mountBlock('not a date');

        expect(wrapper.get('h2').text()).toContain('Jornadas de rol');
        expect(wrapper.find('[data-testid="link-page-featured-countdown"]').exists()).toBe(false);
    });

    it('stops its timers when it goes away', async () => {
        const wrapper = mountBlock('2026-11-13T10:00:00+01:00');

        wrapper.unmount();

        expect(vi.getTimerCount()).toBe(0);
    });
});
