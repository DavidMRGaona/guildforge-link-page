import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { VNode } from 'vue';
import { mount } from '@vue/test-utils';
import Show from '../../resources/js/pages/LinkPage/Show.vue';
import { REPEAT_MS } from '../../resources/js/composables/useLinkPageStats';
import type { LinkPageProps } from '../../resources/js/types/linkPage';

// The core calendar renders its entries as links to their pages; the stand-in renders one
vi.mock('@/components/calendar/ModuleEventCalendar.vue', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        default: defineComponent({
            name: 'ModuleEventCalendar',
            setup() {
                return (): VNode =>
                    h('div', { class: 'fc' }, [
                        h(
                            'a',
                            {
                                class: 'fc-event',
                                href: '/eventos/board-games',
                                'data-testid': 'calendar-entry',
                            },
                            [h('span', { 'data-testid': 'calendar-entry-title' }, 'Board games')]
                        ),
                        h('button', { type: 'button', 'data-testid': 'calendar-next' }, 'Next'),
                    ]);
            },
        }),
    };
});

vi.mock('@/utils/datetime', () => ({ VENUE_TIMEZONE: 'Europe/Madrid' }));

vi.mock('@/utils/cloudinary', () => ({ buildCardImageUrl: (): null => null }));

const ENDPOINT = '/api/link-page/stats';

function props(overrides: Partial<LinkPageProps> = {}): LinkPageProps {
    return {
        site: { name: 'Example guild', logoUrl: null, logoIsDark: false },
        appearance: {
            theme: 'device',
            colors: null,
            background: { type: 'solid', imageUrl: null, angle: 180 },
            buttons: { style: 'outline', shape: 'rounded' },
            font: 'system-ui',
            logo: { shape: 'circle', custom: false },
        },
        intro: null,
        serverNow: '2026-11-14T11:00:00+01:00',
        notice: {
            text: 'Closed on Saturday.',
            tone: 'info',
            link: { url: 'https://example.test/eventos', label: null },
        },
        featured: {
            id: 'event-9',
            title: 'Spring tournament',
            url: 'https://example.test/eventos/spring-tournament',
            startsAt: '2026-11-20T18:00:00+01:00',
            endsAt: '2026-11-20T22:00:00+01:00',
            dayStartsAt: '2026-11-20T00:00:00+01:00',
            spansDays: false,
            location: null,
            imagePublicId: null,
        },
        links: {
            before: [
                {
                    linkId: 'link:0199f3a2-7b1c-7d4e-9a10-3c5b2e8f6d43',
                    title: 'Join our Discord',
                    url: 'https://discord.gg/example',
                    description: null,
                    icon: null,
                    featured: false,
                    external: true,
                },
            ],
            after: [],
        },
        social: { position: 'bottom', links: [] },
        events: [
            {
                id: 'event-1',
                linkId: 'event:event-1',
                title: 'Campaign night',
                url: 'https://example.test/eventos/campaign-night',
                startsAt: null,
                endsAt: null,
                location: null,
                pinned: false,
            },
        ],
        calendar: { initialView: 'list', views: ['list'] },
        calendarFeed: {
            feedUrl: 'https://example.test/calendario.ics',
            webcalUrl: 'webcal://example.test/calendario.ics',
            googleUrl: 'https://calendar.google.com/calendar/r?cid=example',
            outlookUrl: 'https://outlook.live.com/calendar/0/addfromweb?url=example',
        },
        personalCalendar: null,
        homeUrl: 'https://example.test',
        share: { url: 'https://example.test/enlaces', title: 'Example guild', text: '' },
        stats: { endpoint: ENDPOINT, enabled: true },
        ...overrides,
    };
}

/** The bodies sent, as plain objects. */
function sent(beacon: ReturnType<typeof vi.fn>): Record<string, string>[] {
    return beacon.mock.calls.map(([url, body]) => {
        expect(url).toBe(ENDPOINT);

        return Object.fromEntries(body as URLSearchParams);
    });
}

function navigationType(type: NavigationTimingType): void {
    vi.spyOn(performance, 'getEntriesByType').mockReturnValue([
        { type } as PerformanceNavigationTiming,
    ]);
}

describe('LinkPage statistics', () => {
    let beacon: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        beacon = vi.fn(() => true);
        Object.defineProperty(navigator, 'sendBeacon', { value: beacon, configurable: true });
        // jsdom says it is automated, as a test browser should
        Object.defineProperty(navigator, 'webdriver', { value: false, configurable: true });
        navigationType('navigate');
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.useRealTimers();
    });

    it('counts one visit when the page opens', () => {
        mount(Show, { props: props(), attachTo: document.body });

        expect(sent(beacon)).toEqual([{ type: 'visit' }]);
    });

    it.each(['reload', 'back_forward'] as const)('does not count a visit after a %s', (type) => {
        navigationType(type);

        mount(Show, { props: props(), attachTo: document.body });

        expect(beacon).not.toHaveBeenCalled();
    });

    it('sends nothing for people with panel access or automated browsers', async () => {
        const staff = mount(Show, {
            props: props({ stats: { endpoint: ENDPOINT, enabled: false } }),
            attachTo: document.body,
        });
        await staff.find('[data-link-id="home"]').trigger('click');
        staff.unmount();

        Object.defineProperty(navigator, 'webdriver', { value: true, configurable: true });
        mount(Show, { props: props(), attachTo: document.body });

        expect(beacon).not.toHaveBeenCalled();
    });

    it('counts the clicks on own links, event buttons and the site link by their stable id', async () => {
        const wrapper = mount(Show, { props: props(), attachTo: document.body });
        beacon.mockClear();

        await wrapper.find('[data-link-id^="link:"]').trigger('click');
        await wrapper.find('[data-testid="link-page-event"] span').trigger('click');
        await wrapper.find('[data-link-id="home"]').trigger('click');

        expect(sent(beacon)).toEqual([
            { type: 'click', id: 'link:0199f3a2-7b1c-7d4e-9a10-3c5b2e8f6d43' },
            { type: 'click', id: 'event:event-1' },
            { type: 'click', id: 'home' },
        ]);
    });

    it('counts the featured event and the notice link by their own ids', async () => {
        const wrapper = mount(Show, { props: props(), attachTo: document.body });
        beacon.mockClear();

        await wrapper.find('[data-testid="link-page-featured-link"]').trigger('click');
        await wrapper.find('[data-link-id="notice"]').trigger('click');

        expect(sent(beacon)).toEqual([
            { type: 'click', id: 'featured:event-9' },
            { type: 'click', id: 'notice' },
        ]);
    });

    it('counts an entry of the calendar by the address it opens, and nothing else of the calendar', async () => {
        const wrapper = mount(Show, { props: props(), attachTo: document.body });
        beacon.mockClear();

        await wrapper.find('[data-testid="calendar-entry-title"]').trigger('click');
        await wrapper.find('[data-testid="calendar-next"]').trigger('click');

        expect(sent(beacon)).toEqual([
            { type: 'click', id: 'calendar', url: `${window.location.origin}/eventos/board-games` },
        ]);
    });

    it('counts a middle click but not the context menu', async () => {
        const wrapper = mount(Show, { props: props(), attachTo: document.body });
        beacon.mockClear();
        const home = wrapper.find('[data-link-id="home"]');

        await home.trigger('auxclick', { button: 1 });
        await home.trigger('auxclick', { button: 2 });

        expect(sent(beacon)).toEqual([{ type: 'click', id: 'home' }]);
    });

    it('counts a double tap once', async () => {
        vi.useFakeTimers({ toFake: ['Date'] });
        const wrapper = mount(Show, { props: props(), attachTo: document.body });
        beacon.mockClear();
        const home = wrapper.find('[data-link-id="home"]');

        await home.trigger('click');
        await home.trigger('click');
        vi.advanceTimersByTime(REPEAT_MS);
        await home.trigger('click');

        expect(beacon).toHaveBeenCalledTimes(2);
    });

    it('never stops the link when the beacon fails', async () => {
        beacon.mockImplementation(() => {
            throw new Error('blocked');
        });
        const wrapper = mount(Show, { props: props(), attachTo: document.body });
        const home = wrapper.find('[data-link-id="home"]');

        const click = new MouseEvent('click', { bubbles: true, cancelable: true });
        home.element.dispatchEvent(click);

        expect(click.defaultPrevented).toBe(false);
        expect(home.attributes('href')).toBe('https://example.test');
    });

    it('stops listening once the page goes', async () => {
        const wrapper = mount(Show, { props: props(), attachTo: document.body });
        const home = wrapper.find('[data-link-id="home"]').element;
        wrapper.unmount();
        beacon.mockClear();

        document.body.appendChild(home);
        home.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        home.remove();

        expect(beacon).not.toHaveBeenCalled();
    });
});
