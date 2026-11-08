import { describe, expect, it, vi } from 'vitest';
import type { VNode } from 'vue';
import { mount } from '@vue/test-utils';
import Show from '../../resources/js/pages/LinkPage/Show.vue';
import es from '../../resources/js/locales/es';
import type { LinkPageEvent, LinkPageProps } from '../../resources/js/types/linkPage';

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
        title: 'Campaign night',
        url: 'https://example.test/eventos/campaign-night',
        startsAt: '2026-06-16T20:00:00+02:00',
        endsAt: '2026-06-17T01:00:00+02:00',
        location: 'Club room',
        pinned: false,
        ...overrides,
    };
}

function props(overrides: Partial<LinkPageProps> = {}): LinkPageProps {
    return {
        site: { name: 'Example guild', logoUrl: null, logoIsDark: false },
        intro: null,
        events: [],
        calendar: { initialView: 'list', views: ['list', 'month'] },
        homeUrl: 'https://example.test',
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

    it('lists each event as a link to its page with its date at the venue', () => {
        const wrapper = mount(Show, {
            props: props({
                events: [
                    event({ pinned: true }),
                    event({ id: 'event-2', title: 'Skirmish', location: null }),
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
