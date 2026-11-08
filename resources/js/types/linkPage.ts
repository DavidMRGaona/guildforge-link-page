/** The views the core's ModuleEventCalendar takes. */
export type CalendarView = 'list' | 'month';

/** The site as the page shows it at the top. */
export interface LinkPageSite {
    name: string;
    /** The picture at the top (the site logo or the page's own image); null shows the initial. */
    logoUrl: string | null;
    /** Whether the logo is the site's dark-theme one (the site has no light logo). */
    logoIsDark: boolean;
}

/** The pictograms the module draws as inline SVG (resources/js/utils/linkIcons.ts). */
export type LinkPageIconName =
    | 'instagram'
    | 'discord'
    | 'telegram'
    | 'whatsapp'
    | 'youtube'
    | 'twitch'
    | 'x'
    | 'facebook'
    | 'tiktok'
    | 'bluesky'
    | 'email'
    | 'phone'
    | 'website'
    | 'link'
    | 'calendar'
    | 'group'
    | 'book'
    | 'dice'
    | 'ticket'
    | 'map_pin'
    | 'form';

/**
 * One own link. `linkId` never changes (`link:<uuid>`), so the statistics can count its clicks
 * whatever is edited or reordered; the address is always https, mailto or tel.
 */
export interface LinkPageLink {
    linkId: string;
    title: string;
    url: string;
    description: string | null;
    icon: LinkPageIconName | null;
    featured: boolean;
    /** https: leaves for another site, in a new tab; mailto and tel open an app. */
    external: boolean;
}

/** One icon of the social row; `linkId` is `social:<network>`. */
export interface LinkPageSocialLink {
    linkId: string;
    network: string;
    label: string;
    url: string;
    icon: LinkPageIconName;
    external: boolean;
}

export type LinkPageSocialPosition = 'top' | 'bottom';

/** One event button: a published core event, its dates in ISO 8601 with the display offset. */
export interface LinkPageEvent {
    id: string;
    /** Stable id for the statistics: `event:<id>`. */
    linkId: string;
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

/**
 * The addresses to subscribe to the site's public calendar, as the core builds them
 * (`CalendarSubscriptionLinksDTO`): the module only shows them.
 */
export interface LinkPageCalendarFeed {
    /** The https address of the feed, the one to paste into a calendar service. */
    feedUrl: string;
    /** The same address as webcal://, which Apple Calendar and Outlook hand to their app. */
    webcalUrl: string;
    /** Opens Google Calendar with the subscription ready. */
    googleUrl: string;
    /** Opens Outlook on the web with the subscription ready. */
    outlookUrl: string;
}

/** Device follows the class the core puts on the document; light and dark force one set. */
export type LinkPageTheme = 'device' | 'light' | 'dark';

export type LinkPageBackgroundType = 'solid' | 'gradient' | 'image';

export type LinkPageButtonStyle = 'filled' | 'outline' | 'shadow';

export type LinkPageButtonShape = 'square' | 'rounded' | 'pill';

export type LinkPageLogoShape = 'circle' | 'rounded';

/** One colour set, every colour #RRGGBB and checked for WCAG AA on the server. */
export interface LinkPageColors {
    background: string;
    backgroundTo: string;
    text: string;
    button: string;
    buttonText: string;
    /** The selected calendar view button: the button moved away from its text. */
    buttonStrong: string;
    /** The soft background of the calendar: 8 % of the text over the background. */
    muted: string;
    /** Opacity of the background colour over a background image, 0 to 0.9. */
    overlay: number;
}

export interface LinkPageAppearance {
    theme: LinkPageTheme;
    /** Null keeps the site colours, as the core paints them. */
    colors: { light: LinkPageColors; dark: LinkPageColors } | null;
    background: { type: LinkPageBackgroundType; imageUrl: string | null; angle: number };
    buttons: { style: LinkPageButtonStyle; shape: LinkPageButtonShape };
    /** A CSS font-family from the module's fixed list. */
    font: string;
    logo: { shape: LinkPageLogoShape; custom: boolean };
}

/** What the share button passes on: the page's own address and the text of its card. */
export interface LinkPageShare {
    url: string;
    title: string;
    text: string;
}

export type LinkPageNoticeTone = 'info' | 'warning' | 'important';

/** The temporary notice at the top; the server only sends it inside its window. */
export interface LinkPageNotice {
    text: string;
    tone: LinkPageNoticeTone;
    /** An https address or a path of the site; a null label means «Más información». */
    link: { url: string; label: string | null } | null;
}

/** The big event block: dates in ISO 8601 with the display offset, all decided by the server. */
export interface LinkPageFeatured {
    id: string;
    title: string;
    url: string;
    startsAt: string | null;
    endsAt: string | null;
    /** The first instant of the venue day the event starts on: from then on it is «Hoy». */
    dayStartsAt: string | null;
    /** Whether the event ends on a later venue day than it starts. */
    spansDays: boolean;
    location: string | null;
    /** A core image id, turned into a URL with the core's card image helper. */
    imagePublicId: string | null;
}

export interface LinkPageProps {
    site: LinkPageSite;
    appearance: LinkPageAppearance;
    intro: string | null;
    /** The server's own «now» in ISO 8601: the countdown counts from it, not from the device's clock. */
    serverNow: string | null;
    notice: LinkPageNotice | null;
    featured: LinkPageFeatured | null;
    /** Own links around the event buttons, each group in its dragged order. */
    links: { before: LinkPageLink[]; after: LinkPageLink[] };
    social: { position: LinkPageSocialPosition; links: LinkPageSocialLink[] };
    events: LinkPageEvent[];
    calendar: LinkPageCalendar;
    calendarFeed: LinkPageCalendarFeed;
    homeUrl: string;
    share: LinkPageShare;
}
