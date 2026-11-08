import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import ShareButton from '../../resources/js/components/ShareButton.vue';
import es from '../../resources/js/locales/es';
import type { LinkPageShare } from '../../resources/js/types/linkPage';

const share: LinkPageShare = {
    url: 'https://example.test/enlaces',
    title: 'Example guild',
    text: 'Scan, play, repeat.',
};

/** The browser features the button looks at; jsdom has none of them. */
function setNavigator(features: Partial<Navigator>): void {
    for (const key of ['share', 'canShare', 'clipboard'] as const) {
        Object.defineProperty(navigator, key, {
            value: features[key],
            configurable: true,
            writable: true,
        });
    }
}

function mountButton(): ReturnType<typeof mount<typeof ShareButton>> {
    return mount(ShareButton, { props: { share }, attachTo: document.body });
}

async function press(wrapper: ReturnType<typeof mountButton>): Promise<void> {
    await wrapper.get('[data-testid="link-page-share-button"]').trigger('click');
    await flushPromises();
}

describe('ShareButton', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        setNavigator({});
    });

    it('is a named button, with the notice region in the page from the start', () => {
        setNavigator({});
        const wrapper = mountButton();

        const button = wrapper.get('[data-testid="link-page-share-button"]');
        expect(button.element.tagName).toBe('BUTTON');
        expect(button.attributes('type')).toBe('button');
        expect(button.attributes('aria-label')).toBe(es.linkPage.share);

        // Screen readers only announce what changes inside a region that already exists
        const region = wrapper.get('[role="status"]');
        expect(region.attributes('aria-live')).toBe('polite');
        expect(region.text()).toBe('');
    });

    it('opens the share sheet with the title, the text and the address of the page', async () => {
        const nativeShare = vi.fn().mockResolvedValue(undefined);
        const writeText = vi.fn();
        setNavigator({ share: nativeShare, clipboard: { writeText } as unknown as Clipboard });

        await press(mountButton());

        expect(nativeShare).toHaveBeenCalledExactlyOnceWith({
            title: share.title,
            text: share.text,
            url: share.url,
        });
        expect(writeText).not.toHaveBeenCalled();
    });

    it('does nothing more when the person closes the share sheet', async () => {
        const nativeShare = vi.fn().mockRejectedValue(new DOMException('Closed', 'AbortError'));
        const writeText = vi.fn();
        setNavigator({ share: nativeShare, clipboard: { writeText } as unknown as Clipboard });
        const wrapper = mountButton();

        await press(wrapper);

        expect(writeText).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="link-page-share-notice"]').exists()).toBe(false);
    });

    it('copies the address when the share sheet fails for another reason', async () => {
        const nativeShare = vi.fn().mockRejectedValue(new DOMException('No', 'NotAllowedError'));
        const writeText = vi.fn().mockResolvedValue(undefined);
        setNavigator({ share: nativeShare, clipboard: { writeText } as unknown as Clipboard });
        const wrapper = mountButton();

        await press(wrapper);

        expect(writeText).toHaveBeenCalledExactlyOnceWith(share.url);
        expect(wrapper.get('[role="status"]').text()).toBe(es.linkPage.shareCopied);
    });

    it('copies the address, and says so, where there is no share sheet', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        setNavigator({ clipboard: { writeText } as unknown as Clipboard });
        const wrapper = mountButton();

        await press(wrapper);

        expect(writeText).toHaveBeenCalledExactlyOnceWith(share.url);
        expect(wrapper.get('[role="status"]').text()).toBe(es.linkPage.shareCopied);
    });

    it('copies the address when the browser cannot share it', async () => {
        const nativeShare = vi.fn();
        const writeText = vi.fn().mockResolvedValue(undefined);
        setNavigator({
            share: nativeShare,
            canShare: () => false,
            clipboard: { writeText } as unknown as Clipboard,
        });

        await press(mountButton());

        expect(nativeShare).not.toHaveBeenCalled();
        expect(writeText).toHaveBeenCalledExactlyOnceWith(share.url);
    });

    it('falls back to selecting and copying when the clipboard API is refused', async () => {
        const writeText = vi.fn().mockRejectedValue(new DOMException('Denied', 'NotAllowedError'));
        setNavigator({ clipboard: { writeText } as unknown as Clipboard });
        const execCommand = vi.fn().mockReturnValue(true);
        document.execCommand = execCommand;
        const wrapper = mountButton();

        await press(wrapper);

        expect(execCommand).toHaveBeenCalledExactlyOnceWith('copy');
        expect(wrapper.get('[role="status"]').text()).toBe(es.linkPage.shareCopied);
        // The temporary field is not left in the page
        expect(document.querySelector('textarea')).toBeNull();
    });

    it('tells the person when nothing could copy the address', async () => {
        setNavigator({});
        document.execCommand = vi.fn().mockReturnValue(false);
        const wrapper = mountButton();

        await press(wrapper);

        expect(wrapper.get('[role="status"]').text()).toBe(es.linkPage.shareCopyFailed);
    });

    it('takes the notice away after a few seconds', async () => {
        setNavigator({
            clipboard: { writeText: vi.fn().mockResolvedValue(undefined) } as unknown as Clipboard,
        });
        const wrapper = mountButton();

        await press(wrapper);
        expect(wrapper.find('[data-testid="link-page-share-notice"]').exists()).toBe(true);

        vi.advanceTimersByTime(4000);
        await flushPromises();

        expect(wrapper.find('[data-testid="link-page-share-notice"]').exists()).toBe(false);
        expect(wrapper.find('[role="status"]').exists()).toBe(true);
    });
});
