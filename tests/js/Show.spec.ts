import { afterEach, describe, expect, it, vi } from 'vitest';
import type { VNode } from 'vue';
import { mount } from '@vue/test-utils';
import Show from '../../resources/js/pages/LinkPage/Show.vue';
import es from '../../resources/js/locales/es';
import type {
    LinkPageAppearance,
    LinkPageColors,
    LinkPageEvent,
    LinkPageProps,
} from '../../resources/js/types/linkPage';

// The core's calendar loads its entries from the server: only the props it gets are checked
vi.mock('@/components/calendar/ModuleEventCalendar.vue', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        default: defineComponent({
            name: 'ModuleEventCalendar',
            props: {
                initialView: { type: String, default: 'month' },
                views: { type: Array, default: () => [] },
            },
            setup(props) {
                return (): VNode =>
                    h('div', {
                        'data-testid': 'calendar',
                        'data-initial-view': props.initialView,
                        'data-views': props.views.join(','),
                    });
            },
        }),
    };
});

vi.mock('@/utils/datetime', () => ({ VENUE_TIMEZONE: 'Europe/Madrid' }));

function event(overrides: Partial<LinkPageEvent> = {}): LinkPageEvent {
    return {
        id: 'event-1',
        linkId: 'event:event-1',
        title: 'Campaign night',
        url: 'https://example.test/eventos/campaign-night',
        startsAt: '2026-06-16T20:00:00+02:00',
        endsAt: '2026-06-17T01:00:00+02:00',
        location: 'Club room',
        pinned: false,
        ...overrides,
    };
}

function appearance(overrides: Partial<LinkPageAppearance> = {}): LinkPageAppearance {
    return {
        theme: 'device',
        colors: null,
        background: { type: 'solid', imageUrl: null, angle: 180 },
        buttons: { style: 'outline', shape: 'rounded' },
        font: 'var(--font-body, system-ui, sans-serif)',
        logo: { shape: 'circle', custom: false },
        ...overrides,
    };
}

function colors(overrides: Partial<LinkPageColors> = {}): LinkPageColors {
    return {
        background: '#FFFFFF',
        backgroundTo: '#F2F2F2',
        text: '#111111',
        button: '#111111',
        buttonText: '#FFFFFF',
        buttonStrong: '#000000',
        muted: '#EDEDED',
        overlay: 0.6,
        ...overrides,
    };
}

function props(overrides: Partial<LinkPageProps> = {}): LinkPageProps {
    return {
        site: { name: 'Example guild', logoUrl: null, logoIsDark: false },
        appearance: appearance(),
        intro: null,
        serverNow: null,
        notice: null,
        featured: null,
        links: { before: [], after: [] },
        social: { position: 'bottom', links: [] },
        events: [],
        calendar: { initialView: 'list', views: ['list', 'month'] },
        calendarFeed: {
            feedUrl: 'https://example.test/calendario.ics',
            webcalUrl: 'webcal://example.test/calendario.ics',
            googleUrl: 'https://calendar.google.com/calendar/r?cid=example',
            outlookUrl: 'https://outlook.live.com/calendar/0/addfromweb?url=example',
        },
        homeUrl: 'https://example.test',
        share: {
            url: 'https://example.test/enlaces',
            title: 'Example guild',
            text: 'Scan, play, repeat.',
        },
        ...overrides,
    };
}

describe('LinkPage/Show', () => {
    it('shows the site name, its initial without a logo and the presentation text', () => {
        const wrapper = mount(Show, { props: props({ intro: 'Scan, play, repeat.' }) });

        expect(wrapper.find('h1').text()).toBe('Example guild');
        expect(wrapper.find('[data-testid="link-page-initial"]').text()).toBe('E');
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('[data-testid="link-page-intro"]').text()).toBe('Scan, play, repeat.');
    });

    it('shows the logo when the site has one', () => {
        const wrapper = mount(Show, {
            props: props({
                site: {
                    name: 'Example guild',
                    logoUrl: 'https://example.test/logo.png',
                    logoIsDark: false,
                },
            }),
        });

        const logo = wrapper.find('img');
        expect(logo.attributes('src')).toBe('https://example.test/logo.png');
        expect(logo.attributes('alt')).toBe('Logo de Example guild');
        expect(wrapper.find('[data-testid="link-page-initial"]').exists()).toBe(false);
    });

    it('shows no notice and no featured event unless the server sends them', () => {
        const wrapper = mount(Show, { props: props() });

        expect(wrapper.find('[data-testid="link-page-notice"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="link-page-featured"]').exists()).toBe(false);
    });

    it('puts the notice at the very top, above the page content', () => {
        const wrapper = mount(Show, {
            props: props({ notice: { text: 'Closed on Saturday.', tone: 'warning', link: null } }),
        });

        const root = wrapper.get('[data-testid="link-page"]').element;
        expect(root.firstElementChild).toBe(
            wrapper.get('[data-testid="link-page-notice"]').element
        );
        expect(
            wrapper
                .get('main')
                .element.contains(wrapper.get('[data-testid="link-page-notice"]').element)
        ).toBe(false);
    });

    it('puts the featured event between the presentation and the list of events', () => {
        const wrapper = mount(Show, {
            props: props({
                serverNow: '2026-06-15T10:00:00+02:00',
                featured: {
                    id: 'event-9',
                    title: 'Big convention',
                    url: 'https://example.test/eventos/big-convention',
                    startsAt: '2026-06-20T10:00:00+02:00',
                    endsAt: '2026-06-20T20:00:00+02:00',
                    dayStartsAt: '2026-06-20T00:00:00+02:00',
                    spansDays: false,
                    location: null,
                    imagePublicId: null,
                },
            }),
        });

        const featured = wrapper.get('[data-testid="link-page-featured"]').element;
        const header = wrapper.get('header').element;
        const events = wrapper.get('#link-page-events').element;
        expect(
            header.compareDocumentPosition(featured) & Node.DOCUMENT_POSITION_FOLLOWING
        ).toBeTruthy();
        expect(
            featured.compareDocumentPosition(events) & Node.DOCUMENT_POSITION_FOLLOWING
        ).toBeTruthy();
    });

    it('offers a share button, first in the page for keyboards and screen readers', () => {
        const wrapper = mount(Show, { props: props() });

        const button = wrapper.get('[data-testid="link-page-share-button"]');
        expect(button.attributes('aria-label')).toBe(es.linkPage.share);
        expect(wrapper.get('main').element.firstElementChild).toBe(
            wrapper.get('[data-testid="link-page-share"]').element
        );
    });

    it('lists each event as a link to its page with its date at the venue', () => {
        const wrapper = mount(Show, {
            props: props({
                events: [
                    event({ pinned: true }),
                    event({
                        id: 'event-2',
                        linkId: 'event:event-2',
                        title: 'Skirmish',
                        location: null,
                    }),
                ],
            }),
        });

        const links = wrapper.findAll('[data-testid="link-page-event"]');
        expect(links).toHaveLength(2);
        expect(links[0]?.attributes('href')).toBe('https://example.test/eventos/campaign-night');
        expect(links[0]?.text()).toContain(es.linkPage.pinned);
        expect(links[0]?.text()).toContain('Club room');
        expect(links[0]?.find('time').attributes('datetime')).toBe('2026-06-16T20:00:00+02:00');
        expect(links[0]?.find('time').text()).toContain('20:00');
        expect(links[1]?.text()).not.toContain(es.linkPage.pinned);
        expect(wrapper.find('[data-testid="link-page-no-events"]').exists()).toBe(false);
    });

    it('says so when there are no upcoming events', () => {
        const wrapper = mount(Show, { props: props() });

        expect(wrapper.find('[data-testid="link-page-no-events"]').text()).toBe(
            es.linkPage.noEvents
        );
    });

    it('puts the subscribe button at the end of the calendar section', () => {
        const wrapper = mount(Show, { props: props() });

        const section = wrapper.get('section[aria-labelledby="link-page-calendar"]');
        const subscribe = section.get('[data-testid="link-page-subscribe"]');
        const calendar = section.get('[data-testid="calendar"]');
        expect(section.element.lastElementChild).toBe(subscribe.element);
        expect(
            calendar.element.compareDocumentPosition(subscribe.element) &
                Node.DOCUMENT_POSITION_FOLLOWING
        ).toBeTruthy();
        expect(subscribe.get('[data-testid="link-page-subscribe-button"]').text()).toBe(
            es.linkPage.subscribe.button
        );
    });

    it('gives the calendar its initial view and both views', () => {
        const wrapper = mount(Show, {
            props: props({ calendar: { initialView: 'month', views: ['list', 'month'] } }),
        });

        const calendar = wrapper.find('[data-testid="calendar"]');
        expect(calendar.attributes('data-initial-view')).toBe('month');
        expect(calendar.attributes('data-views')).toBe('list,month');
    });

    it('ends with a link to the whole site', () => {
        const wrapper = mount(Show, { props: props() });

        const home = wrapper.find('footer a');
        expect(home.attributes('href')).toBe('https://example.test');
        expect(home.text()).toBe('Ir a la web de Example guild');
    });
});

describe('LinkPage/Show appearance', () => {
    afterEach(() => {
        document.documentElement.classList.remove('dark');
    });

    it('keeps the site colours by default: no colour variables, bordered buttons', () => {
        const wrapper = mount(Show, { props: props({ events: [event()] }) });
        const root = wrapper.find('[data-testid="link-page"]');

        expect(root.classes()).toEqual(
            expect.arrayContaining([
                'link-page',
                'link-page--buttons-outline',
                'link-page--shape-rounded',
                'link-page--background-solid',
            ])
        );
        expect(root.classes()).not.toContain('link-page--custom');
        expect(root.attributes('style')).not.toContain('--lp-light-bg');
        expect(root.attributes('style')).toContain('--lp-font: var(--font-body');
        expect(wrapper.find('[data-testid="link-page-event"]').classes()).toContain(
            'link-page-button'
        );
    });

    it('turns both colour sets into variables and marks the page as custom', () => {
        const wrapper = mount(Show, {
            props: props({
                appearance: appearance({
                    colors: {
                        light: colors(),
                        dark: colors({ background: '#0A0A0A', text: '#F5F5F5', overlay: 0.4 }),
                    },
                    background: { type: 'gradient', imageUrl: null, angle: 135 },
                    buttons: { style: 'shadow', shape: 'pill' },
                }),
            }),
        });
        const root = wrapper.find('[data-testid="link-page"]');
        const style = root.attributes('style') ?? '';

        expect(root.classes()).toEqual(
            expect.arrayContaining([
                'link-page--custom',
                'link-page--buttons-shadow',
                'link-page--shape-pill',
                'link-page--background-gradient',
            ])
        );
        expect(style).toContain('--lp-light-bg: #FFFFFF');
        expect(style).toContain('--lp-light-button-strong: #000000');
        expect(style).toContain('--lp-light-muted: #EDEDED');
        expect(style).toContain('--lp-dark-bg: #0A0A0A');
        expect(style).toContain('--lp-dark-text: #F5F5F5');
        expect(style).toContain('--lp-dark-overlay: 0.4');
        expect(style).toContain('--lp-angle: 135deg');
    });

    it('paints a background image under an overlay, hidden from assistive technology', () => {
        const wrapper = mount(Show, {
            props: props({
                appearance: appearance({
                    colors: { light: colors(), dark: colors() },
                    background: {
                        type: 'image',
                        imageUrl: 'https://example.test/back.jpg',
                        angle: 180,
                    },
                }),
            }),
        });

        const background = wrapper.find('[data-testid="link-page-background"]');
        expect(background.attributes('src')).toBe('https://example.test/back.jpg');
        expect(background.attributes('alt')).toBe('');
        expect(wrapper.find('.link-page__overlay').attributes('aria-hidden')).toBe('true');
    });

    it('fills its own logo in the chosen shape, without the site plate', () => {
        const wrapper = mount(Show, {
            props: props({
                site: {
                    name: 'Example guild',
                    logoUrl: 'https://example.test/own.png',
                    logoIsDark: false,
                },
                appearance: appearance({ logo: { shape: 'rounded', custom: true } }),
            }),
        });

        const logo = wrapper.find('[data-testid="link-page-logo"]');
        expect(logo.classes()).toEqual(expect.arrayContaining(['rounded-2xl', 'object-cover']));
        expect(logo.classes()).not.toContain('bg-surface');
    });

    it('forces the chosen theme on the document and gives the previous one back', () => {
        document.documentElement.classList.add('dark');

        const light = mount(Show, { props: props({ appearance: appearance({ theme: 'light' }) }) });
        expect(document.documentElement.classList.contains('dark')).toBe(false);
        light.unmount();
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        document.documentElement.classList.remove('dark');
        const dark = mount(Show, { props: props({ appearance: appearance({ theme: 'dark' }) }) });
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        dark.unmount();
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('leaves the document theme alone when it follows the device', () => {
        document.documentElement.classList.add('dark');

        mount(Show, { props: props() });

        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });
});
