<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { buildCardImageUrl } from '@/utils/cloudinary';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import { useFeaturedCountdown } from '../composables/useFeaturedCountdown';
import type { LinkPageFeatured } from '../types/linkPage';

/**
 * The big event block: image, date, place and a countdown. Everything that decides what it says
 * comes from the server as instants (when the venue day begins, when the event starts and ends,
 * and the server's own «now»); the page only counts. The ticking numbers are hidden from screen
 * readers, which get the date as plain text, and the «Hoy» / «Ahora mismo» label sits in a polite
 * status region that is always in the page, so it is announced once when it changes.
 */
const props = defineProps<{ featured: LinkPageFeatured; serverNow: string | null }>();

const { t, locale } = useI18n();

const { phase, parts, showSeconds } = useFeaturedCountdown(
    () => ({
        serverNow: props.serverNow,
        dayStartsAt: props.featured.dayStartsAt,
        startsAt: props.featured.startsAt,
        endsAt: props.featured.endsAt,
    }),
    // The event is over: the server says which one comes next, or that there is none
    () => {
        router.reload({ only: ['featured', 'events', 'serverNow'] });
    }
);

const imageUrl = computed(() => buildCardImageUrl(props.featured.imagePublicId));

const statusLabel = computed(() => {
    if (phase.value === 'today') {
        return t('linkPage.featuredToday');
    }

    return phase.value === 'live' ? t('linkPage.featuredLive') : '';
});

const countdownText = computed(() => {
    const left = parts.value;
    if (left === null) {
        return null;
    }

    if (left.days > 0) {
        return `${t('linkPage.countdownDays', { n: left.days })} ${t('linkPage.countdownHours', { n: left.hours })}`;
    }

    if (left.hours > 0) {
        return `${t('linkPage.countdownHours', { n: left.hours })} ${t('linkPage.countdownMinutes', { n: left.minutes })}`;
    }

    if (showSeconds.value) {
        return `${t('linkPage.countdownMinutes', { n: left.minutes })} ${t('linkPage.countdownSeconds', { n: left.seconds })}`;
    }

    return left.minutes > 0
        ? t('linkPage.countdownMinutes', { n: left.minutes })
        : t('linkPage.countdownLessThanMinute');
});

function toDate(iso: string | null): Date | null {
    if (iso === null) {
        return null;
    }

    const date = new Date(iso);

    return Number.isNaN(date.getTime()) ? null : date;
}

// Dates as announced at the venue, whatever the visitor's own time zone
function formatter(options: Intl.DateTimeFormatOptions): Intl.DateTimeFormat {
    return new Intl.DateTimeFormat(locale.value, { timeZone: VENUE_TIMEZONE, ...options });
}

const startsAt = computed(() => toDate(props.featured.startsAt));
const endsAt = computed(() => toDate(props.featured.endsAt));

const dateText = computed(() => {
    const start = startsAt.value;
    if (start === null) {
        return null;
    }

    const hour = formatter({ hour: '2-digit', minute: '2-digit' }).format(start);
    const end = endsAt.value;

    if (props.featured.spansDays && end !== null) {
        const days = formatter({ day: 'numeric', month: 'short' }).formatRange(start, end);

        return `${days}, ${hour}`;
    }

    return formatter({
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(start);
});

const untilText = computed(() => {
    const end = endsAt.value;
    if (phase.value !== 'live' || !props.featured.spansDays || end === null) {
        return null;
    }

    const date = formatter({ weekday: 'short', day: 'numeric', month: 'short' }).format(end);

    return t('linkPage.featuredUntil', { date });
});
</script>

<template>
    <section
        class="link-page-featured"
        aria-labelledby="link-page-featured-title"
        data-testid="link-page-featured"
    >
        <img
            v-if="imageUrl"
            :src="imageUrl"
            alt=""
            class="link-page-featured__image"
            width="640"
            height="360"
        />
        <div class="flex flex-col gap-3 p-4">
            <p class="link-page-button__badge w-fit rounded-full px-2 py-0.5 text-xs font-semibold">
                {{ t('linkPage.featuredEyebrow') }}
            </p>
            <h2 id="link-page-featured-title" class="break-words text-xl font-bold leading-tight">
                {{ featured.title }}
            </h2>
            <p v-if="dateText" class="text-sm">
                <time :datetime="featured.startsAt ?? undefined">{{ dateText }}</time>
                <template v-if="featured.location"> · {{ featured.location }}</template>
            </p>
            <p v-else-if="featured.location" class="text-sm">{{ featured.location }}</p>

            <div role="status" class="min-h-0" data-testid="link-page-featured-label">
                <span
                    v-if="statusLabel"
                    class="link-page-featured__label inline-block rounded-full px-3 py-1 text-sm font-semibold"
                >
                    {{ statusLabel }}
                </span>
            </div>
            <p v-if="untilText" class="text-sm" data-testid="link-page-featured-until">
                {{ untilText }}
            </p>

            <p
                v-if="countdownText"
                class="link-page-featured__countdown flex flex-wrap items-baseline gap-x-2 rounded-lg px-3 py-2"
                aria-hidden="true"
                data-testid="link-page-featured-countdown"
            >
                <span class="text-sm">{{ t('linkPage.featuredStartsIn') }}</span>
                <span
                    class="text-xl font-bold tabular-nums"
                    data-testid="link-page-featured-countdown-value"
                    >{{ countdownText }}</span
                >
            </p>

            <a
                :href="featured.url"
                class="link-page-button flex min-h-12 items-center justify-center px-4 py-3 text-center font-semibold"
                data-testid="link-page-featured-link"
            >
                {{ t('linkPage.featuredCta') }}<span class="sr-only">: {{ featured.title }}</span>
            </a>
        </div>
    </section>
</template>
