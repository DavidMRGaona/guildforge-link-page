import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import NoticeBanner from '../../resources/js/components/NoticeBanner.vue';
import es from '../../resources/js/locales/es';
import type { LinkPageNotice } from '../../resources/js/types/linkPage';

const notice: LinkPageNotice = {
    text: 'Este sábado el local está cerrado.',
    tone: 'warning',
    link: null,
};

function mountNotice(
    overrides: Partial<LinkPageNotice> = {}
): ReturnType<typeof mount<typeof NoticeBanner>> {
    return mount(NoticeBanner, { props: { notice: { ...notice, ...overrides } } });
}

describe('NoticeBanner', () => {
    it('is a polite status that does not interrupt screen readers', () => {
        const banner = mountNotice().get('[data-testid="link-page-notice"]');

        expect(banner.attributes('role')).toBe('status');
        expect(banner.attributes('aria-live')).toBeUndefined();
    });

    it('shows the text with the name of its tone, never colour alone', () => {
        const wrapper = mountNotice();

        expect(wrapper.text()).toContain(es.linkPage.noticeTones.warning);
        expect(wrapper.text()).toContain('Este sábado el local está cerrado.');
    });

    it.each([
        ['info', es.linkPage.noticeTones.info],
        ['warning', es.linkPage.noticeTones.warning],
        ['important', es.linkPage.noticeTones.important],
    ] as const)('names the %s tone and styles it', (tone, name) => {
        const banner = mountNotice({ tone }).get('[data-testid="link-page-notice"]');

        expect(banner.classes()).toContain(`link-page-notice--${tone}`);
        expect(banner.text()).toContain(name);
    });

    it('has a decorative icon per tone', () => {
        const icon = mountNotice().get('svg');

        expect(icon.attributes('aria-hidden')).toBe('true');
    });

    it('has no link without one', () => {
        expect(mountNotice().find('a').exists()).toBe(false);
    });

    it('links with its own label', () => {
        const link = mountNotice({
            link: { url: 'https://example.test/news', label: 'Ver novedades' },
        }).get('a');

        expect(link.attributes('href')).toBe('https://example.test/news');
        expect(link.text()).toBe('Ver novedades');
    });

    it('links with «Más información» when it has no label', () => {
        const link = mountNotice({ link: { url: '/eventos', label: null } }).get('a');

        expect(link.attributes('href')).toBe('/eventos');
        expect(link.text()).toBe(es.linkPage.noticeMoreInfo);
    });
});
