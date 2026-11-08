<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Contracts;

use Modules\LinkPage\Domain\Enums\CalendarView;
use Modules\LinkPage\Domain\ValueObjects\Appearance;

/**
 * The page settings: release defaults (config/settings.php) with what the panel saved on top,
 * read defensively so a malformed stored value falls back instead of breaking the page.
 */
interface LinkPageSettingsInterface
{
    public const int DEFAULT_EVENTS = 5;

    public const int MAX_EVENTS = 20;

    public const int DEFAULT_WITHIN_DAYS = 60;

    public const int MAX_WITHIN_DAYS = 365;

    public const int MAX_INTRO_LENGTH = 500;

    /** What a link preview shows as the title; link previews cut anything longer. */
    public const int MAX_SHARE_TITLE_LENGTH = 70;

    /** The core cuts every description at 160 characters. */
    public const int MAX_SHARE_DESCRIPTION_LENGTH = 160;

    /** Whether /enlaces answers; it is a 404 otherwise. */
    public function isEnabled(): bool;

    /** The text under the site name, or null when there is none. */
    public function intro(): ?string;

    /** At most this many event buttons, pinned ones included. */
    public function eventsLimit(): int;

    /** Display days the automatic events reach, today being the first. */
    public function eventsWithinDays(): int;

    /**
     * Only automatic events with any of these tags; none means every event.
     *
     * @return list<string>
     */
    public function eventTagSlugs(): array;

    /**
     * Events shown first while they have not ended.
     *
     * @return list<string>
     */
    public function pinnedEventIds(): array;

    /**
     * Events never shown, pinned or not.
     *
     * @return list<string>
     */
    public function hiddenEventIds(): array;

    public function calendarInitialView(): CalendarView;

    /** The title of the share card, or null for the site name. */
    public function shareTitle(): ?string;

    /** The description of the share card, or null for the intro (and then the site's description). */
    public function shareDescription(): ?string;

    /**
     * The image of the share card, a file the share form stored on the images disk, or null for
     * the site logo. Any other stored path is ignored.
     */
    public function shareImage(): ?string;

    /** Whether search engines are asked not to index the page. Off unless saved as true. */
    public function noindex(): bool;

    /**
     * How the page looks. A malformed stored value gives the defaults; stored colours below
     * WCAG AA give the site colours, keeping the rest.
     */
    public function appearance(): Appearance;
}
