/** The views the core's ModuleEventCalendar takes. */
export type CalendarView = 'list' | 'month';

/** The site as the page shows it at the top. */
export interface LinkPageSite {
    name: string;
    logoUrl: string | null;
    /** Whether the logo is the dark-theme one (the site has no light logo). */
    logoIsDark: boolean;
}

/** One event button: a published core event, its dates in ISO 8601 with the display offset. */
export interface LinkPageEvent {
    id: string;
    title: string;
    url: string;
    startsAt: string | null;
    endsAt: string | null;
    location: string | null;
    pinned: boolean;
}

export interface LinkPageCalendar {
    initialView: CalendarView;
    views: CalendarView[];
}

export interface LinkPageProps {
    site: LinkPageSite;
    intro: string | null;
    events: LinkPageEvent[];
    calendar: LinkPageCalendar;
    homeUrl: string;
}
