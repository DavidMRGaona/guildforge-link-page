<script setup lang="ts">
import { computed, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import ModuleEventCalendar from '@/components/calendar/ModuleEventCalendar.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import CalendarSubscribe from '../../components/CalendarSubscribe.vue';
import PersonalCalendarLink from '../../components/PersonalCalendarLink.vue';
import FeaturedEvent from '../../components/FeaturedEvent.vue';
import NoticeBanner from '../../components/NoticeBanner.vue';
import ShareButton from '../../components/ShareButton.vue';
import OwnLinkButton from '../../components/OwnLinkButton.vue';
import SocialRow from '../../components/SocialRow.vue';
import { useLinkPageStats } from '../../composables/useLinkPageStats';
import type { LinkPageColors, LinkPageEvent, LinkPageProps } from '../../types/linkPage';

/**
 * /enlaces, the page a printed QR code leads to: no site header, menu or footer
 * (its prefix does not use the core layout), laid out for a phone first.
 *
 * The appearance arrives as values from closed lists and #RRGGBB colours checked on the
 * server; they only become CSS variables here, and module.css turns them into styles.
 */
const props = defineProps<LinkPageProps>();

useLinkPageStats(props.stats);

const { t, locale } = useI18n();

const initial = computed(() => props.site.name.trim().charAt(0).toLocaleUpperCase());

// A forced theme sets the class the core reads (its palette and the calendar follow it)
// before the first render; the page has no content before Vue mounts, so nothing flashes
const root = typeof document !== 'undefined' ? document.documentElement : null;
const previousDark = root?.classList.contains('dark') ?? false;
if (root !== null && props.appearance.theme !== 'device') {
    root.classList.toggle('dark', props.appearance.theme === 'dark');
}
onBeforeUnmount(() => {
    if (root !== null && props.appearance.theme !== 'device') {
        root.classList.toggle('dark', previousDark);
    }
});

const COLOR_KEYS: Record<Exclude<keyof LinkPageColors, 'overlay'>, string> = {
    background: 'bg',
    backgroundTo: 'bg-to',
    text: 'text',
    button: 'button',
    buttonText: 'button-text',
    buttonStrong: 'button-strong',
    muted: 'muted',
};

const rootStyle = computed<Record<string, string>>(() => {
    const { appearance } = props;
    const style: Record<string, string> = {
        '--lp-font': appearance.font,
        '--lp-angle': `${appearance.background.angle}deg`,
    };

    if (appearance.colors !== null) {
        for (const set of ['light', 'dark'] as const) {
            const colors = appearance.colors[set];
            for (const [key, name] of Object.entries(COLOR_KEYS)) {
                style[`--lp-${set}-${name}`] = colors[key as keyof typeof COLOR_KEYS];
            }
            style[`--lp-${set}-overlay`] = String(colors.overlay);
        }
    }

    return style;
});

const rootClass = computed(() => {
    const { appearance } = props;

    return [
        'link-page',
        `link-page--buttons-${appearance.buttons.style}`,
        `link-page--shape-${appearance.buttons.shape}`,
        `link-page--background-${appearance.background.type}`,
        { 'link-page--custom': appearance.colors !== null },
    ];
});

const backgroundImage = computed(() =>
    props.appearance.background.type === 'image' ? props.appearance.background.imageUrl : null
);

const logoShapeClass = computed(() =>
    props.appearance.logo.shape === 'circle' ? 'rounded-full' : 'rounded-2xl'
);

// Dates as announced at the venue, whatever the visitor's own time zone
const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            timeZone: VENUE_TIMEZONE,
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        })
);

function formatStart(event: LinkPageEvent): string | null {
    if (event.startsAt === null) {
        return null;
    }

    const date = new Date(event.startsAt);

    return Number.isNaN(date.getTime()) ? null : dateFormatter.value.format(date);
}
</script>

<template>
    <div
        :class="rootClass"
        :style="rootStyle"
        class="relative isolate min-h-screen bg-page text-base-primary"
        data-testid="link-page"
    >
        <template v-if="backgroundImage">
            <img
                :src="backgroundImage"
                alt=""
                class="pointer-events-none fixed inset-0 -z-20 size-full object-cover"
                data-testid="link-page-background"
            />
            <div
                class="link-page__overlay pointer-events-none fixed inset-0 -z-10"
                aria-hidden="true"
            />
        </template>

        <NoticeBanner v-if="notice" :notice="notice" />

        <main
            class="relative mx-auto flex w-full max-w-xl flex-col gap-10 px-4 pb-10 pt-10 sm:px-6"
        >
            <ShareButton :share="share" />

            <header class="flex flex-col items-center gap-3 text-center">
                <img
                    v-if="site.logoUrl"
                    :src="site.logoUrl"
                    :alt="t('linkPage.logoAlt', { name: site.name })"
                    :class="[
                        'size-24',
                        logoShapeClass,
                        appearance.logo.custom
                            ? 'object-cover'
                            : [
                                  'object-contain p-2',
                                  site.logoIsDark ? 'bg-neutral-900' : 'bg-surface',
                              ],
                    ]"
                    width="96"
                    height="96"
                    data-testid="link-page-logo"
                />
                <span
                    v-else
                    :class="[
                        'link-page__initial flex size-24 items-center justify-center text-4xl font-bold',
                        logoShapeClass,
                    ]"
                    aria-hidden="true"
                    data-testid="link-page-initial"
                >
                    {{ initial }}
                </span>
                <h1 class="text-2xl font-bold tracking-tight">{{ site.name }}</h1>
                <p
                    v-if="intro"
                    class="max-w-prose whitespace-pre-line text-base text-base-secondary"
                    data-testid="link-page-intro"
                >
                    {{ intro }}
                </p>
                <SocialRow v-if="social.position === 'top'" :links="social.links" class="mt-1" />
            </header>

            <FeaturedEvent v-if="featured" :featured="featured" :server-now="serverNow" />

            <section
                v-if="links.before.length > 0"
                :aria-label="t('linkPage.links')"
                data-testid="link-page-links-before"
            >
                <ul class="flex list-none flex-col gap-3 p-0">
                    <li v-for="link in links.before" :key="link.linkId">
                        <OwnLinkButton :link="link" />
                    </li>
                </ul>
            </section>

            <section aria-labelledby="link-page-events">
                <h2 id="link-page-events" class="mb-3 text-lg font-semibold">
                    {{ t('linkPage.upcomingEvents') }}
                </h2>
                <ul v-if="events.length > 0" class="flex list-none flex-col gap-3 p-0">
                    <li v-for="event in events" :key="event.id">
                        <a
                            :href="event.url"
                            :data-link-id="event.linkId"
                            class="link-page-button flex min-h-14 flex-col justify-center px-4 py-3"
                            data-testid="link-page-event"
                        >
                            <span class="flex items-center gap-2 font-semibold">
                                <span
                                    v-if="event.pinned"
                                    class="link-page-button__badge rounded-full px-2 py-0.5 text-xs font-semibold"
                                >
                                    {{ t('linkPage.pinned') }}
                                </span>
                                <span class="min-w-0 break-words">{{ event.title }}</span>
                            </span>
                            <span
                                v-if="formatStart(event)"
                                class="link-page-button__meta mt-0.5 text-sm"
                            >
                                <time :datetime="event.startsAt ?? undefined">{{
                                    formatStart(event)
                                }}</time>
                                <template v-if="event.location"> · {{ event.location }}</template>
                            </span>
                        </a>
                    </li>
                </ul>
                <p v-else class="text-base-secondary" data-testid="link-page-no-events">
                    {{ t('linkPage.noEvents') }}
                </p>
            </section>

            <section
                v-if="links.after.length > 0"
                :aria-label="t('linkPage.links')"
                data-testid="link-page-links-after"
            >
                <ul class="flex list-none flex-col gap-3 p-0">
                    <li v-for="link in links.after" :key="link.linkId">
                        <OwnLinkButton :link="link" />
                    </li>
                </ul>
            </section>

            <section aria-labelledby="link-page-calendar">
                <h2 id="link-page-calendar" class="mb-3 text-lg font-semibold">
                    {{ t('linkPage.calendar') }}
                </h2>
                <div class="link-page__calendar">
                    <ModuleEventCalendar
                        :initial-view="calendar.initialView"
                        :views="calendar.views"
                    />
                </div>
                <CalendarSubscribe :links="calendarFeed" class="mt-4">
                    <template v-if="personalCalendar" #actions>
                        <PersonalCalendarLink :personal-calendar="personalCalendar" />
                    </template>
                </CalendarSubscribe>
            </section>

            <SocialRow v-if="social.position === 'bottom'" :links="social.links" />

            <footer class="text-center">
                <a
                    :href="homeUrl"
                    data-link-id="home"
                    class="link-page__link inline-flex min-h-11 items-center text-sm text-base-secondary underline underline-offset-2 hover:text-base-primary"
                >
                    {{ t('linkPage.visitSite', { name: site.name }) }}
                </a>
            </footer>
        </main>
    </div>
</template>
