import { onBeforeUnmount, onMounted } from 'vue';
import type { LinkPageStats } from '../types/linkPage';

/** The wrapper of the core calendar: its entries carry no id, so they count by address. */
const CALENDAR_SELECTOR = '.link-page__calendar a[href]';

/** A second click on the same thing this soon is the same click (a double tap). */
export const REPEAT_MS = 2000;

type Beacon = { type: 'visit' } | { type: 'click'; id: string; url?: string };

interface NavigatorWithWebdriver {
    webdriver?: boolean;
}

interface DocumentWithPrerendering {
    prerendering?: boolean;
}

/**
 * Anonymous counts of /enlaces: one visit per load of the page (reloads and back or forward
 * steps left out, nothing stored in the browser to know it) and the clicks on whatever has a
 * `data-link-id` or is an entry of the calendar. The links themselves never wait for this
 * and never go through the site: a beacon that fails changes nothing for the person.
 */
export function useLinkPageStats(stats: LinkPageStats): void {
    const lastSent = new Map<string, number>();

    const send = (beacon: Beacon): void => {
        try {
            const body = new URLSearchParams({ type: beacon.type });
            if (beacon.type === 'click') {
                body.set('id', beacon.id);
                if (beacon.url !== undefined) {
                    body.set('url', beacon.url);
                }
            }
            if (typeof navigator.sendBeacon === 'function') {
                navigator.sendBeacon(stats.endpoint, body);
                return;
            }
            void fetch(stats.endpoint, { method: 'POST', body, keepalive: true, credentials: 'omit' }).catch(
                () => undefined,
            );
        } catch {
            // Counting never gets in the way of the page
        }
    };

    const countVisit = (): void => {
        const [navigation] = performance.getEntriesByType?.('navigation') ?? [];
        const type = (navigation as PerformanceNavigationTiming | undefined)?.type;
        if (type === 'reload' || type === 'back_forward') {
            return;
        }
        send({ type: 'visit' });
    };

    const beaconFor = (target: EventTarget | null): Beacon | null => {
        if (!(target instanceof Element)) {
            return null;
        }

        const marked = target.closest<HTMLElement>('[data-link-id]');
        if (marked?.dataset.linkId) {
            return { type: 'click', id: marked.dataset.linkId };
        }

        const entry = target.closest<HTMLAnchorElement>(CALENDAR_SELECTOR);
        if (entry?.href) {
            return { type: 'click', id: 'calendar', url: entry.href };
        }

        return null;
    };

    const countClick = (event: MouseEvent): void => {
        // Primary click (also Enter on a link) and the middle button; never the context menu
        if (event.button !== 0 && event.button !== 1) {
            return;
        }

        const beacon = beaconFor(event.target);
        if (beacon === null || beacon.type !== 'click') {
            return;
        }

        const key = `${beacon.id} ${beacon.url ?? ''}`;
        const now = Date.now();
        const last = lastSent.get(key);
        if (last !== undefined && now - last < REPEAT_MS) {
            return;
        }
        lastSent.set(key, now);
        send(beacon);
    };

    onMounted(() => {
        // Automated browsers (tests, audits) say so; people with panel access are not counted
        if (!stats.enabled || (navigator as NavigatorWithWebdriver).webdriver === true) {
            return;
        }

        // A page the browser prepares in advance only counts once the person opens it
        if ((document as DocumentWithPrerendering).prerendering === true) {
            document.addEventListener('prerenderingchange', countVisit, { once: true });
        } else {
            countVisit();
        }

        // Capture: the calendar handles its own clicks and the page may leave right after
        document.addEventListener('click', countClick, true);
        document.addEventListener('auxclick', countClick, true);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('prerenderingchange', countVisit);
        document.removeEventListener('click', countClick, true);
        document.removeEventListener('auxclick', countClick, true);
    });
}
