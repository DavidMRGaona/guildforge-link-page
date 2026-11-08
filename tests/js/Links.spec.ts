import { describe, expect, it, vi } from 'vitest';
import type { VNode } from 'vue';
import { mount } from '@vue/test-utils';
import Show from '../../resources/js/pages/LinkPage/Show.vue';
import OwnLinkButton from '../../resources/js/components/OwnLinkButton.vue';
import SocialRow from '../../resources/js/components/SocialRow.vue';
import es from '../../resources/js/locales/es';
import { LINK_ICONS } from '../../resources/js/utils/linkIcons';
import type {
    LinkPageIconName,
    LinkPageLink,
    LinkPageProps,
    LinkPageSocialLink,
} from '../../resources/js/types/linkPage';

vi.mock('@/components/calendar/ModuleEventCalendar.vue', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        default: defineComponent({
            name: 'ModuleEventCalendar',
            setup() {
                return (): VNode => h('div', { 'data-testid': 'calendar' });
            },
        }),
    };
});

vi.mock('@/utils/datetime', () => ({ VENUE_TIMEZONE: 'Europe/Madrid' }));

function link(overrides: Partial<LinkPageLink> = {}): LinkPageLink {
    return {
        linkId: 'link:0b4f8c1e',
        title: 'Join our Discord',
        url: 'https://discord.gg/example',
        description: null,
        icon: null,
        featured: false,
        external: true,
        ...overrides,
    };
}

function social(overrides: Partial<LinkPageSocialLink> = {}): LinkPageSocialLink {
    return {
        linkId: 'social:instagram',
        network: 'instagram',
        label: 'Instagram',
        url: 'https://www.instagram.com/example',
        icon: 'instagram',
        external: true,
        ...overrides,
    };
}

function props(overrides: Partial<LinkPageProps> = {}): LinkPageProps {
    return {
        site: { name: 'Example guild', logoUrl: null, logoIsDark: false },
        appearance: {
            theme: 'device',
            colors: null,
            background: { type: 'solid', imageUrl: null, angle: 180 },
            buttons: { style: 'outline', shape: 'rounded' },
            font: 'var(--font-body, system-ui, sans-serif)',
            logo: { shape: 'circle', custom: false },
        },
        intro: null,
        links: { before: [], after: [] },
        social: { position: 'bottom', links: [] },
        events: [
            {
                id: 'event-1',
                linkId: 'event:event-1',
                title: 'Campaign night',
                url: 'https://example.test/eventos/campaign-night',
                startsAt: '2026-06-16T20:00:00+02:00',
                endsAt: null,
                location: null,
                pinned: false,
            },
        ],
        calendar: { initialView: 'list', views: ['list', 'month'] },
        homeUrl: 'https://example.test',
        share: { url: 'https://example.test/enlaces', title: 'Example guild', text: 'Scan, play, repeat.' },
        ...overrides,
    };
}

describe('OwnLinkButton', () => {
    it('opens a web address in a new tab without leaking the opener or the referrer', () => {
        const wrapper = mount(OwnLinkButton, { props: { link: link() } });
        const anchor = wrapper.find('a');

        expect(anchor.attributes('href')).toBe('https://discord.gg/example');
        expect(anchor.attributes('target')).toBe('_blank');
        expect(anchor.attributes('rel')).toBe('noopener noreferrer');
    });

    it('lets mail and phone addresses open their app, in the same tab', () => {
        for (const url of ['mailto:info@example.test', 'tel:+34600123456']) {
            const anchor = mount(OwnLinkButton, {
                props: { link: link({ url, external: false }) },
            }).find('a');

            expect(anchor.attributes('href')).toBe(url);
            expect(anchor.attributes('target')).toBeUndefined();
            expect(anchor.attributes('rel')).toBeUndefined();
        }
    });

    it('is named by its title and says it opens a new tab, only when it does', () => {
        const external = mount(OwnLinkButton, { props: { link: link() } });
        const mail = mount(OwnLinkButton, {
            props: { link: link({ url: 'mailto:info@example.test', external: false }) },
        });

        expect(external.text()).toContain('Join our Discord');
        expect(external.find('.sr-only').text()).toBe(es.linkPage.opensInNewTab);
        expect(mail.find('.sr-only').exists()).toBe(false);
    });

    it('carries its stable id for the statistics', () => {
        const anchor = mount(OwnLinkButton, { props: { link: link() } }).find('a');

        expect(anchor.attributes('data-link-id')).toBe('link:0b4f8c1e');
    });

    it('shows the description and the icon only when it has them', () => {
        const plain = mount(OwnLinkButton, { props: { link: link() } });
        const rich = mount(OwnLinkButton, {
            props: { link: link({ description: 'Chat with the guild', icon: 'discord' }) },
        });

        expect(plain.find('[data-testid="link-page-link-description"]').exists()).toBe(false);
        expect(plain.find('svg').exists()).toBe(false);
        expect(rich.find('[data-testid="link-page-link-description"]').text()).toBe(
            'Chat with the guild'
        );
        expect(rich.find('svg').attributes('data-icon')).toBe('discord');
        expect(rich.find('svg').attributes('aria-hidden')).toBe('true');
    });

    it('is bigger, badged and ringed when featured, and plain otherwise', () => {
        const plain = mount(OwnLinkButton, { props: { link: link() } }).find('a');
        const featured = mount(OwnLinkButton, { props: { link: link({ featured: true }) } });

        expect(plain.classes()).toContain('link-page-button');
        expect(plain.classes()).not.toContain('link-page-button--featured');
        expect(featured.find('a').classes()).toContain('link-page-button--featured');
        expect(featured.find('[data-testid="link-page-link-badge"]').text()).toBe(
            es.linkPage.featured
        );
        expect(plain.find('[data-testid="link-page-link-badge"]').exists()).toBe(false);
    });

    it('reaches the 44 px touch target even without a description', () => {
        const classes = mount(OwnLinkButton, { props: { link: link() } })
            .find('a')
            .classes();

        // min-h-14 is 56 px; the featured one min-h-20 is 80 px
        expect(classes).toContain('min-h-14');
    });
});

describe('SocialRow', () => {
    it('draws nothing without networks', () => {
        const wrapper = mount(SocialRow, { props: { links: [] } });

        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('lists each network as an icon-only link named after it', () => {
        const wrapper = mount(SocialRow, {
            props: {
                links: [
                    social(),
                    social({
                        linkId: 'social:email',
                        network: 'email',
                        label: 'Correo electrónico',
                        url: 'mailto:info@example.test',
                        icon: 'email',
                        external: false,
                    }),
                ],
            },
        });

        const anchors = wrapper.findAll('[data-testid="link-page-social-link"]');

        expect(wrapper.find('nav').attributes('aria-label')).toBe(es.linkPage.social);
        expect(anchors).toHaveLength(2);
        expect(anchors[0]?.text()).toBe(`Instagram, ${es.linkPage.opensInNewTabShort}`);
        expect(anchors[0]?.attributes('target')).toBe('_blank');
        expect(anchors[0]?.attributes('rel')).toBe('noopener noreferrer');
        expect(anchors[0]?.attributes('data-link-id')).toBe('social:instagram');
        expect(anchors[0]?.classes()).toContain('size-11');
        expect(anchors[1]?.text()).toBe('Correo electrónico');
        expect(anchors[1]?.attributes('target')).toBeUndefined();
        expect(anchors[1]?.attributes('rel')).toBeUndefined();
        expect(anchors[1]?.attributes('href')).toBe('mailto:info@example.test');
        expect(anchors[1]?.find('svg').attributes('aria-hidden')).toBe('true');
    });
});

describe('keyboard focus', () => {
    it('never cancels the focus outline the page paints on its buttons and links', () => {
        // `focus:outline-none` sets outline-style: none, which beats the page's own
        // `.link-page-button:focus-visible` ring: keyboard users would see nothing
        const wrapper = mount(Show, {
            props: props({
                links: { before: [link()], after: [] },
                social: { position: 'top', links: [social()] },
            }),
        });

        const targets = wrapper.findAll('a, button');

        expect(targets.length).toBeGreaterThanOrEqual(5);
        for (const target of targets) {
            expect(target.classes(), target.html()).not.toContain('focus:outline-none');
        }
    });
});

describe('the icon set', () => {
    it('has a drawing for every name the server may send', () => {
        const names: LinkPageIconName[] = [
            'instagram', 'discord', 'telegram', 'whatsapp', 'youtube', 'twitch', 'x',
            'facebook', 'tiktok', 'bluesky', 'email', 'phone', 'website', 'link',
            'calendar', 'group', 'book', 'dice', 'ticket', 'map_pin', 'form',
        ]; // prettier-ignore

        expect(Object.keys(LINK_ICONS).sort()).toEqual([...names].sort());

        for (const name of names) {
            const icon = LINK_ICONS[name];
            expect(icon.shapes.length, name).toBeGreaterThan(0);
            expect(icon.viewBox, name).toMatch(/^0 0 \d+ \d+$/);
        }
    });

    it('draws nothing instead of failing for a name it does not know', () => {
        const wrapper = mount(OwnLinkButton, {
            props: { link: link({ icon: 'myspace' as LinkPageIconName }) },
        });

        expect(wrapper.find('svg').exists()).toBe(false);
        expect(wrapper.text()).toContain('Join our Discord');
    });
});

describe('LinkPage/Show with own links and social networks', () => {
    it('puts the links before and after the events in their groups', () => {
        const wrapper = mount(Show, {
            props: props({
                links: {
                    before: [link({ linkId: 'link:a', title: 'Before one' })],
                    after: [link({ linkId: 'link:b', title: 'After one' })],
                },
            }),
        });

        const html = wrapper.html();
        const before = html.indexOf('Before one');
        const events = html.indexOf('Campaign night');
        const after = html.indexOf('After one');
        const calendar = html.indexOf('data-testid="calendar"');

        expect(before).toBeGreaterThan(-1);
        expect(before).toBeLessThan(events);
        expect(events).toBeLessThan(after);
        expect(after).toBeLessThan(calendar);
    });

    it('draws no empty sections without links', () => {
        const wrapper = mount(Show, { props: props() });

        expect(wrapper.find('[data-testid="link-page-links-before"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="link-page-links-after"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="link-page-social"]').exists()).toBe(false);
    });

    it('puts the social row under the name or at the end, as configured', () => {
        const links = [social()];
        const top = mount(Show, { props: props({ social: { position: 'top', links } }) });
        const bottom = mount(Show, { props: props({ social: { position: 'bottom', links } }) });

        expect(top.find('header [data-testid="link-page-social"]').exists()).toBe(true);
        expect(top.findAll('[data-testid="link-page-social"]')).toHaveLength(1);
        expect(bottom.find('header [data-testid="link-page-social"]').exists()).toBe(false);
        expect(bottom.findAll('[data-testid="link-page-social"]')).toHaveLength(1);
        expect(bottom.html().indexOf('data-testid="link-page-social"')).toBeGreaterThan(
            bottom.html().indexOf('data-testid="calendar"')
        );
    });

    it('marks every event button with a stable id too', () => {
        const wrapper = mount(Show, { props: props() });

        expect(wrapper.find('[data-testid="link-page-event"]').attributes('data-link-id')).toBe(
            'event:event-1'
        );
    });

    it('gives each clickable element a different id', () => {
        const wrapper = mount(Show, {
            props: props({
                links: {
                    before: [link({ linkId: 'link:a' })],
                    after: [link({ linkId: 'link:b' })],
                },
                social: { position: 'top', links: [social()] },
            }),
        });

        const ids = wrapper
            .findAll('[data-link-id]')
            .map((node) => node.attributes('data-link-id'));

        expect(ids).toEqual(['social:instagram', 'link:a', 'event:event-1', 'link:b']);
        expect(new Set(ids).size).toBe(ids.length);
    });
});
