<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import LinkIcon from './LinkIcon.vue';
import type { LinkPagePersonalCalendar } from '../types/linkPage';

/**
 * «Mi calendario»: the signed-in person's own calendar subscription (only what they are signed
 * up for). It goes in the `actions` slot of `CalendarSubscribe`, next to «Suscribirse».
 *
 * It leads to the calendar tab of the profile instead of showing the feed address: the core
 * keeps only the hash of that address and hands it out once, when the person issues it there
 * (which also revokes the previous one), so a visit to this page must never issue a token.
 * A plain link of the page (`link-page-button`: its colours, shape and focus ring), 48 px tall.
 * Its basis is wider than «Suscribirse»: on a phone the two do not fit side by side, so each takes
 * its own row instead of wrapping the label onto two lines.
 */
defineProps<{ personalCalendar: LinkPagePersonalCalendar }>();

const { t } = useI18n();
</script>

<template>
    <a
        :href="personalCalendar.profileUrl"
        class="link-page-button flex min-h-12 grow basis-44 items-center justify-center gap-2 px-4 py-2 font-semibold"
        data-testid="link-page-my-calendar"
    >
        <LinkIcon name="ticket" class="size-5 shrink-0" />
        {{ t('linkPage.myCalendar.button') }}
        <span class="sr-only">. {{ t('linkPage.myCalendar.hint') }}</span>
    </a>
</template>
