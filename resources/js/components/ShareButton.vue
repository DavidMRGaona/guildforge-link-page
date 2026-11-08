<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { LinkPageShare } from '../types/linkPage';

/**
 * «Compartir»: the phone's own share sheet (Web Share API) when the browser has one, else the
 * address of the page is copied. Either way the person is told, in a polite live region that is
 * always in the page so screen readers announce what is put into it.
 */
const props = defineProps<{ share: LinkPageShare }>();

const { t } = useI18n();

const NOTICE_MS = 4000;

const notice = ref('');
let noticeTimer: ReturnType<typeof setTimeout> | undefined;

function say(message: string): void {
    notice.value = message;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => {
        notice.value = '';
    }, NOTICE_MS);
}

onBeforeUnmount(() => {
    clearTimeout(noticeTimer);
});

/** Whether the browser can open a share sheet for this page. */
function canShareNatively(data: ShareData): boolean {
    if (typeof navigator.share !== 'function') {
        return false;
    }

    return typeof navigator.canShare === 'function' ? navigator.canShare(data) : true;
}

async function writeToClipboard(text: string): Promise<boolean> {
    if (typeof navigator.clipboard?.writeText === 'function') {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Denied or not in a secure context: the older way below may still work
        }
    }

    // execCommand is deprecated but is all an insecure origin or an old browser has
    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();

    try {
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        field.remove();
    }
}

async function copyLink(): Promise<void> {
    say(
        (await writeToClipboard(props.share.url))
            ? t('linkPage.shareCopied')
            : t('linkPage.shareCopyFailed')
    );
}

async function onShare(): Promise<void> {
    const data: ShareData = {
        title: props.share.title,
        text: props.share.text,
        url: props.share.url,
    };

    if (!canShareNatively(data)) {
        await copyLink();

        return;
    }

    try {
        await navigator.share(data);
    } catch (error) {
        // Closing the sheet is not a failure
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        await copyLink();
    }
}
</script>

<template>
    <div class="absolute right-4 top-3 z-10 sm:right-6" data-testid="link-page-share">
        <button
            type="button"
            class="link-page-button flex size-11 cursor-pointer items-center justify-center"
            :aria-label="t('linkPage.share')"
            :title="t('linkPage.share')"
            data-testid="link-page-share-button"
            @click="onShare"
        >
            <svg
                class="size-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
                focusable="false"
            >
                <circle cx="18" cy="5" r="3" />
                <circle cx="6" cy="12" r="3" />
                <circle cx="18" cy="19" r="3" />
                <path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4" />
            </svg>
        </button>
        <div role="status" aria-live="polite" aria-atomic="true">
            <p
                v-if="notice"
                class="absolute right-0 top-full mt-2 w-max max-w-[min(18rem,calc(100vw-2rem))] rounded-lg border bg-surface px-3 py-2 text-sm text-base-primary shadow-md"
                style="border-color: var(--color-border-strong)"
                data-testid="link-page-share-notice"
            >
                {{ notice }}
            </p>
        </div>
    </div>
</template>
