<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import LinkIcon from './LinkIcon.vue';
import type { LinkPageCalendarFeed } from '../types/linkPage';
import { writeToClipboard } from '../utils/clipboard';

/**
 * «Suscribirse» at the end of the calendar section: one button that opens the ways to follow
 * the site's public calendar without an account (the feed of the core, whose addresses arrive
 * already built): the phone's calendar app (webcal://), Google Calendar, Outlook on the web
 * and copying the address.
 *
 * The options open below the button instead of floating over the page, so they never run off
 * a narrow screen. Every control is a button of the page (`link-page-button`: its colours,
 * shape and focus ring), at least 48 px tall.
 */
const props = defineProps<{ links: LinkPageCalendarFeed }>();

const { t } = useI18n();

const NOTICE_MS = 6000;

const optionsId = `link-page-subscribe-${useId()}`;
const isOpen = ref(false);
const notice = ref('');
const showAddress = ref(false);
const trigger = ref<HTMLButtonElement | null>(null);
const address = ref<HTMLInputElement | null>(null);
let noticeTimer: ReturnType<typeof setTimeout> | undefined;

// Chrome, Firefox, Edge, Opera and the Google app on iPhone do not hand webcal:// to Calendar
const isIosOtherBrowser =
    typeof navigator !== 'undefined' && /CriOS|FxiOS|EdgiOS|OPiOS|GSA\//.test(navigator.userAgent);

const optionClass =
    'link-page-button flex min-h-12 w-full cursor-pointer items-center gap-3 px-4 py-2 text-left font-semibold';

/**
 * Puts a message in the polite live region, which is always in the page so screen readers
 * announce what is put into it. A failure stays until the options are closed.
 */
function say(message: string, lasting = false): void {
    notice.value = message;
    clearTimeout(noticeTimer);
    if (!lasting) {
        noticeTimer = setTimeout(() => {
            notice.value = '';
        }, NOTICE_MS);
    }
}

function clearNotice(): void {
    clearTimeout(noticeTimer);
    notice.value = '';
    showAddress.value = false;
}

onBeforeUnmount(() => {
    clearTimeout(noticeTimer);
});

function toggle(): void {
    isOpen.value = !isOpen.value;
    if (!isOpen.value) {
        clearNotice();
    }
}

function closeAndFocusTrigger(): void {
    if (!isOpen.value) {
        return;
    }

    isOpen.value = false;
    clearNotice();
    trigger.value?.focus();
}

async function copyAddress(): Promise<void> {
    if (await writeToClipboard(props.links.feedUrl)) {
        showAddress.value = false;
        say(t('linkPage.subscribe.copied'));

        return;
    }

    // Nothing could copy it: the address is shown, selected, to copy by hand
    showAddress.value = true;
    say(t('linkPage.subscribe.copyFailed'), true);
    await nextTick();
    address.value?.select();
}
</script>

<template>
    <div
        class="link-page__subscribe flex flex-col gap-3"
        data-testid="link-page-subscribe"
        @keydown.escape="closeAndFocusTrigger"
    >
        <div class="flex flex-wrap gap-3" data-testid="link-page-subscribe-actions">
            <button
                ref="trigger"
                type="button"
                :class="[
                    'link-page-button flex min-h-12 grow basis-40 cursor-pointer items-center justify-center gap-2 px-4 py-2 font-semibold',
                ]"
                :aria-expanded="isOpen"
                :aria-controls="isOpen ? optionsId : undefined"
                data-testid="link-page-subscribe-button"
                @click="toggle"
            >
                <LinkIcon name="calendar" class="size-5 shrink-0" />
                {{ t('linkPage.subscribe.button') }}
                <svg
                    class="size-4 shrink-0 transition-transform"
                    :class="{ 'rotate-180': isOpen }"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path d="M6 9l6 6 6-6" />
                </svg>
            </button>
        </div>

        <div v-if="isOpen">
            <ul
                :id="optionsId"
                :aria-label="t('linkPage.subscribe.optionsLabel')"
                class="flex list-none flex-col gap-3 p-0"
                data-testid="link-page-subscribe-options"
            >
                <li>
                    <a
                        :href="links.webcalUrl"
                        :class="optionClass"
                        data-testid="link-page-subscribe-app"
                    >
                        <LinkIcon name="calendar" class="size-5 shrink-0" />
                        {{ t('linkPage.subscribe.app') }}
                    </a>
                </li>
                <li>
                    <a
                        :href="links.googleUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        :class="optionClass"
                        data-testid="link-page-subscribe-google"
                    >
                        <LinkIcon name="website" class="size-5 shrink-0" />
                        {{ t('linkPage.subscribe.google') }}
                        <span class="sr-only">{{ t('linkPage.opensInNewTab') }}</span>
                    </a>
                </li>
                <li>
                    <a
                        :href="links.outlookUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        :class="optionClass"
                        data-testid="link-page-subscribe-outlook"
                    >
                        <LinkIcon name="email" class="size-5 shrink-0" />
                        {{ t('linkPage.subscribe.outlook') }}
                        <span class="sr-only">{{ t('linkPage.opensInNewTab') }}</span>
                    </a>
                </li>
                <li>
                    <button
                        type="button"
                        :class="optionClass"
                        data-testid="link-page-subscribe-copy"
                        @click="copyAddress"
                    >
                        <LinkIcon name="link" class="size-5 shrink-0" />
                        {{ t('linkPage.subscribe.copy') }}
                    </button>
                </li>
            </ul>
        </div>

        <!-- Always in the page, closed or open, so that screen readers announce what is put
             into it; the negative margin takes back the gap while it is empty -->
        <div
            role="status"
            aria-live="polite"
            aria-atomic="true"
            :class="{ '-mt-3': notice === '' }"
        >
            <p
                v-if="notice"
                class="text-sm font-medium text-base-primary"
                data-testid="link-page-subscribe-notice"
            >
                {{ notice }}
            </p>
        </div>

        <template v-if="isOpen">
            <input
                v-if="showAddress"
                ref="address"
                type="text"
                readonly
                :value="links.feedUrl"
                :aria-label="t('linkPage.subscribe.addressLabel')"
                class="link-page__address min-h-12 w-full rounded-lg border bg-surface px-3 text-sm text-base-primary"
                style="border-color: var(--color-border-strong)"
                data-testid="link-page-subscribe-address"
                @focus="address?.select()"
            />
            <p class="text-sm text-base-secondary" data-testid="link-page-subscribe-hint">
                {{ t('linkPage.subscribe.hint') }}
            </p>
            <p
                v-if="isIosOtherBrowser"
                class="text-sm text-base-secondary"
                data-testid="link-page-subscribe-ios-hint"
            >
                {{ t('linkPage.subscribe.iosOtherBrowserHint') }}
            </p>
        </template>
    </div>
</template>
