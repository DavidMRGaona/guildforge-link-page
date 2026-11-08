<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ModuleEventCalendar from '@/components/calendar/ModuleEventCalendar.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type { LinkPageEvent, LinkPageProps } from '../../types/linkPage';

/**
 * /enlaces, the page a printed QR code leads to: no site header, menu or footer
 * (its prefix does not use the core layout), laid out for a phone first.
 */
const props = defineProps<LinkPageProps>();

const { t, locale } = useI18n();

const initial = computed(() => props.site.name.trim().charAt(0).toLocaleUpperCase());

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
    <div class="min-h-screen bg-page text-base-primary">
        <main class="mx-auto flex w-full max-w-xl flex-col gap-10 px-4 pb-10 pt-10 sm:px-6">
            <header class="flex flex-col items-center gap-3 text-center">
                <img
                    v-if="site.logoUrl"
                    :src="site.logoUrl"
                    :alt="t('linkPage.logoAlt', { name: site.name })"
                    :class="[
                        'size-24 rounded-full object-contain p-2',
                        site.logoIsDark ? 'bg-neutral-900' : 'bg-surface',
                    ]"
                    width="96"
                    height="96"
                />
                <span
                    v-else
                    class="flex size-24 items-center justify-center rounded-full bg-primary text-4xl font-bold text-white"
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
            </header>

            <section aria-labelledby="link-page-events">
                <h2 id="link-page-events" class="mb-3 text-lg font-semibold">
                    {{ t('linkPage.upcomingEvents') }}
                </h2>
                <ul v-if="events.length > 0" class="flex list-none flex-col gap-3 p-0">
                    <li v-for="event in events" :key="event.id">
                        <a
                            :href="event.url"
                            class="flex min-h-14 flex-col justify-center rounded-xl border border-default bg-surface px-4 py-3 shadow-sm transition-colors hover:border-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            data-testid="link-page-event"
                        >
                            <span class="flex items-center gap-2 font-semibold">
                                <span
                                    v-if="event.pinned"
                                    class="rounded-full bg-primary-light px-2 py-0.5 text-xs font-semibold text-base-primary"
                                >
                                    {{ t('linkPage.pinned') }}
                                </span>
                                <span class="min-w-0 break-words">{{ event.title }}</span>
                            </span>
                            <span
                                v-if="formatStart(event)"
                                class="mt-0.5 text-sm text-base-secondary"
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

            <section aria-labelledby="link-page-calendar">
                <h2 id="link-page-calendar" class="mb-3 text-lg font-semibold">
                    {{ t('linkPage.calendar') }}
                </h2>
                <ModuleEventCalendar :initial-view="calendar.initialView" :views="calendar.views" />
            </section>

            <footer class="text-center">
                <a
                    :href="homeUrl"
                    class="inline-flex min-h-11 items-center text-sm text-base-secondary underline underline-offset-2 hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                >
                    {{ t('linkPage.visitSite', { name: site.name }) }}
                </a>
            </footer>
        </main>
    </div>
</template>
