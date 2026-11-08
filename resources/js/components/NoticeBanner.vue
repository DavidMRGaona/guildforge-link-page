<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { LinkPageNotice } from '../types/linkPage';

/**
 * The temporary notice, right at the top. A polite status (`role="status"`): it is part of the
 * page from the start, so screen readers read it with the page and are never interrupted. The
 * tone is told by the icon, the border and the word before the text, never by colour alone; the
 * colours are pairs the page already validated (module.css).
 */
const props = defineProps<{ notice: LinkPageNotice }>();

const { t } = useI18n();

// Outline paths of a circled «i», a warning triangle and a circled «!»
const ICONS = {
    info: 'M12 16v-4m0-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    warning:
        'M12 9v3.75m0 3.75h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z',
    important: 'M12 8v5m0 3.5h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
} as const;

const icon = computed(() => ICONS[props.notice.tone]);
</script>

<template>
    <div
        :class="['link-page-notice', `link-page-notice--${notice.tone}`]"
        role="status"
        data-testid="link-page-notice"
    >
        <div class="mx-auto flex w-full max-w-xl items-start gap-3 px-4 py-3 sm:px-6">
            <svg
                class="mt-0.5 size-5 shrink-0"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path :d="icon" />
            </svg>
            <p class="min-w-0 break-words text-sm leading-snug">
                <strong class="font-semibold"
                    >{{ t(`linkPage.noticeTones.${notice.tone}`) }}:</strong
                >
                {{ ' ' }}{{ notice.text }}
                <template v-if="notice.link">
                    {{ ' ' }}
                    <a
                        :href="notice.link.url"
                        data-link-id="notice"
                        class="link-page-notice__link font-semibold underline underline-offset-2"
                    >
                        {{ notice.link.label ?? t('linkPage.noticeMoreInfo') }}
                    </a>
                </template>
            </p>
        </div>
    </div>
</template>
