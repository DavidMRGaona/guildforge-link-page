<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use InvalidArgumentException;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Domain\Enums\CalendarView;
use Modules\LinkPage\Domain\ValueObjects\Appearance;

/**
 * Reads config('modules.settings.link-page'), where the core loads the release defaults
 * deep-merged with the values saved from the panel.
 */
final readonly class LinkPageSettings implements LinkPageSettingsInterface
{
    private const string KEY = 'modules.settings.link-page';

    public function isEnabled(): bool
    {
        return (bool) config(self::KEY.'.enabled', true);
    }

    public function intro(): ?string
    {
        $intro = config(self::KEY.'.intro');

        if (! is_string($intro) || trim($intro) === '') {
            return null;
        }

        return mb_substr(trim($intro), 0, self::MAX_INTRO_LENGTH);
    }

    public function eventsLimit(): int
    {
        return $this->boundedInt(self::KEY.'.events.limit', self::DEFAULT_EVENTS, self::MAX_EVENTS);
    }

    public function eventsWithinDays(): int
    {
        return $this->boundedInt(self::KEY.'.events.within_days', self::DEFAULT_WITHIN_DAYS, self::MAX_WITHIN_DAYS);
    }

    public function eventTagSlugs(): array
    {
        return $this->strings(self::KEY.'.events.tags');
    }

    public function pinnedEventIds(): array
    {
        return $this->strings(self::KEY.'.events.pinned');
    }

    public function hiddenEventIds(): array
    {
        return $this->strings(self::KEY.'.events.hidden');
    }

    public function calendarInitialView(): CalendarView
    {
        $view = config(self::KEY.'.calendar.initial_view');

        return (is_string($view) ? CalendarView::tryFrom($view) : null) ?? CalendarView::List;
    }

    public function appearance(): Appearance
    {
        $stored = config(self::KEY.'.appearance');
        $defaults = Appearance::defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        try {
            $appearance = Appearance::fromArray(array_replace_recursive($defaults->toArray(), $stored));
        } catch (InvalidArgumentException) {
            return $defaults;
        }

        return $appearance->contrastFailures() === [] ? $appearance : $appearance->withSiteColors();
    }

    /**
     * A whole number from 1 to $max: the default below 1 or when it is not a number, $max above it.
     */
    private function boundedInt(string $key, int $default, int $max): int
    {
        $value = config($key);

        if (! is_numeric($value) || (int) $value < 1) {
            return $default;
        }

        return min((int) $value, $max);
    }

    /**
     * The non-blank strings of a list, trimmed, without repeats; anything else is ignored.
     *
     * @return list<string>
     */
    private function strings(string $key): array
    {
        $values = config($key);

        if (! is_array($values)) {
            return [];
        }

        $strings = array_map(trim(...), array_filter($values, is_string(...)));

        return array_values(array_unique(array_filter($strings, fn (string $value): bool => $value !== '')));
    }
}
