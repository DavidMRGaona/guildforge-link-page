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

    /**
     * How the page looks. A malformed stored value gives the defaults; stored colours below
     * WCAG AA give the site colours, keeping the rest.
     */
    public function appearance(): Appearance;
}
