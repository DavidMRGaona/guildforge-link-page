import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import CalendarSubscribe from '../../resources/js/components/CalendarSubscribe.vue';
import es from '../../resources/js/locales/es';
import type { LinkPageCalendarFeed } from '../../resources/js/types/linkPage';

const links: LinkPageCalendarFeed = {
    feedUrl: 'https://example.test/calendario.ics',
    webcalUrl: 'webcal://example.test/calendario.ics',
    googleUrl:
        'https://calendar.google.com/calendar/r?cid=webcal%3A%2F%2Fexample.test%2Fcalendario.ics',
    outlookUrl: 'https://outlook.live.com/calendar/0/addfromweb?url=https%3A%2F%2Fexample.test',
};

/** The browser features the component looks at; the test DOM has none of them. */
function setNavigator(features: Partial<Navigator>, userAgent = 'Mozilla/5.0 Safari'): void {
    Object.defineProperty(navigator, 'clipboard', {
        value: features.clipboard,
        configurable: true,
        writable: true,
    });
    Object.defineProperty(navigator, 'userAgent', {
        value: userAgent,
        configurable: true,
    });
}

function mountSubscribe(
    slots: Record<string, string> = {}
): ReturnType<typeof mount<typeof CalendarSubscribe>> {
    return mount(CalendarSubscribe, { props: { links }, slots, attachTo: document.body });
}

async function open(wrapper: ReturnType<typeof mountSubscribe>): Promise<void> {
    await wrapper.get('[data-testid="link-page-subscribe-button"]').trigger('click');
}

describe('CalendarSubscribe', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        setNavigator({});
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        setNavigator({});
    });

    it('is a named button that starts closed, with the notice region in the page from the start', () => {
        const wrapper = mountSubscribe();

        const button = wrapper.get('[data-testid="link-page-subscribe-button"]');
        expect(button.element.tagName).toBe('BUTTON');
        expect(button.attributes('type')).toBe('button');
        expect(button.text()).toBe(es.linkPage.subscribe.button);
        expect(button.attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('[data-testid="link-page-subscribe-options"]').exists()).toBe(false);

        // Screen readers only announce what changes inside a region that already exists
        const region = wrapper.get('[role="status"]');
        expect(region.attributes('aria-live')).toBe('polite');
        expect(region.text()).toBe('');
    });

    it('opens the options, and the button points at them', async () => {
        const wrapper = mountSubscribe();

        await open(wrapper);

        const button = wrapper.get('[data-testid="link-page-subscribe-button"]');
        const options = wrapper.get('[data-testid="link-page-subscribe-options"]');
        expect(button.attributes('aria-expanded')).toBe('true');
        expect(button.attributes('aria-controls')).toBe(options.attributes('id'));
        expect(options.attributes('aria-label')).toBe(es.linkPage.subscribe.optionsLabel);
    });

    it('offers the addresses the server built, as they come', async () => {
        const wrapper = mountSubscribe();
        await open(wrapper);

        const app = wrapper.get('[data-testid="link-page-subscribe-app"]');
        expect(app.attributes('href')).toBe(links.webcalUrl);
        expect(app.attributes('target')).toBeUndefined();

        const google = wrapper.get('[data-testid="link-page-subscribe-google"]');
        expect(google.attributes('href')).toBe(links.googleUrl);
        expect(google.attributes('target')).toBe('_blank');
        expect(google.attributes('rel')).toBe('noopener noreferrer');
        expect(google.find('.sr-only').text()).toBe(es.linkPage.opensInNewTab);

        const outlook = wrapper.get('[data-testid="link-page-subscribe-outlook"]');
        expect(outlook.attributes('href')).toBe(links.outlookUrl);
        expect(outlook.attributes('target')).toBe('_blank');
        expect(outlook.attributes('rel')).toBe('noopener noreferrer');
        expect(outlook.find('.sr-only').text()).toBe(es.linkPage.opensInNewTab);
    });

    it('draws every option as a page button with room to tap', async () => {
        const wrapper = mountSubscribe();
        await open(wrapper);

        const controls = [
            wrapper.get('[data-testid="link-page-subscribe-button"]'),
            wrapper.get('[data-testid="link-page-subscribe-app"]'),
            wrapper.get('[data-testid="link-page-subscribe-google"]'),
            wrapper.get('[data-testid="link-page-subscribe-outlook"]'),
            wrapper.get('[data-testid="link-page-subscribe-copy"]'),
        ];
        for (const control of controls) {
            expect(control.classes()).toContain('link-page-button');
            // min-h-12 is 48 px, above the 44 px a finger needs
            expect(control.classes()).toContain('min-h-12');
            // The ring comes from .link-page-button:focus-visible: nothing may switch it off
            expect(control.classes()).not.toContain('focus:outline-none');
        }
    });

    it('copies the https address, and says what to do with it', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        setNavigator({ clipboard: { writeText } as unknown as Clipboard });
        const wrapper = mountSubscribe();
        await open(wrapper);

        await wrapper.get('[data-testid="link-page-subscribe-copy"]').trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledExactlyOnceWith(links.feedUrl);
        expect(wrapper.get('[role="status"]').text()).toBe(es.linkPage.subscribe.copied);
        expect(es.linkPage.subscribe.copied).toBe(
            'Dirección copiada. Pégala en tu app de calendario.'
        );
    });

    it('takes the copy notice away after a few seconds', async () => {
        setNavigator({
            clipboard: { writeText: vi.fn().mockResolvedValue(undefined) } as unknown as Clipboard,
        });
        const wrapper = mountSubscribe();
        await open(wrapper);
        await wrapper.get('[data-testid="link-page-subscribe-copy"]').trigger('click');
        await flushPromises();

        vi.advanceTimersByTime(6000);
        await flushPromises();

        expect(wrapper.get('[role="status"]').text()).toBe('');
        expect(wrapper.find('[role="status"]').exists()).toBe(true);
    });

    it('falls back to selecting and copying when the clipboard API is refused', async () => {
        const writeText = vi.fn().mockRejectedValue(new DOMException('Denied', 'NotAllowedError'));
        setNavigator({ clipboard: { writeText } as unknown as Clipboard });
        const execCommand = vi.fn().mockReturnValue(true);
        document.execCommand = execCommand;
        const wrapper = mountSubscribe();
        await open(wrapper);

        await wrapper.get('[data-testid="link-page-subscribe-copy"]').trigger('click');
        await flushPromises();

        expect(execCommand).toHaveBeenCalledExactlyOnceWith('copy');
        expect(wrapper.get('[role="status"]').text()).toBe(es.linkPage.subscribe.copied);
        expect(document.querySelector('textarea')).toBeNull();
    });

    it('shows the address to copy by hand when nothing could copy it', async () => {
        document.execCommand = vi.fn().mockReturnValue(false);
        const wrapper = mountSubscribe();
        await open(wrapper);

        await wrapper.get('[data-testid="link-page-subscribe-copy"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="status"]').text()).toBe(es.linkPage.subscribe.copyFailed);
        const field = wrapper.get<HTMLInputElement>('[data-testid="link-page-subscribe-address"]');
        expect(field.element.value).toBe(links.feedUrl);
        expect(field.attributes('readonly')).toBeDefined();
        expect(field.attributes('aria-label')).toBe(es.linkPage.subscribe.addressLabel);
    });

    it('closes with Escape and gives the focus back to the button', async () => {
        const wrapper = mountSubscribe();
        await open(wrapper);
        wrapper.get<HTMLAnchorElement>('[data-testid="link-page-subscribe-app"]').element.focus();

        await wrapper
            .get('[data-testid="link-page-subscribe"]')
            .trigger('keydown', { key: 'Escape' });

        const button = wrapper.get('[data-testid="link-page-subscribe-button"]');
        expect(button.attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('[data-testid="link-page-subscribe-options"]').exists()).toBe(false);
        expect(document.activeElement).toBe(button.element);
    });

    it('tells people on iPhone with another browser that Calendar will not open from it', async () => {
        setNavigator({}, 'Mozilla/5.0 (iPhone) AppleWebKit/605 CriOS/130 Mobile/15E148 Safari/604');
        const wrapper = mountSubscribe();
        await open(wrapper);

        expect(wrapper.get('[data-testid="link-page-subscribe-ios-hint"]').text()).toBe(
            es.linkPage.subscribe.iosOtherBrowserHint
        );
    });

    it('does not tell it to anyone else', async () => {
        const wrapper = mountSubscribe();
        await open(wrapper);

        expect(wrapper.find('[data-testid="link-page-subscribe-ios-hint"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="link-page-subscribe-hint"]').text()).toBe(
            es.linkPage.subscribe.hint
        );
    });

    it('leaves room next to the button for another action', () => {
        const wrapper = mountSubscribe({ actions: '<a data-testid="other">Mi calendario</a>' });

        const row = wrapper.get('[data-testid="link-page-subscribe-actions"]');
        expect(row.find('[data-testid="other"]').exists()).toBe(true);
        expect(row.find('[data-testid="link-page-subscribe-button"]').exists()).toBe(true);
    });
});
