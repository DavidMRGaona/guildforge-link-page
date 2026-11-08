<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use InvalidArgumentException;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\Contracts\SiteSocialProfilesInterface;
use Modules\LinkPage\Domain\Enums\CalendarView;
use Modules\LinkPage\Domain\Enums\SocialNetwork;
use Modules\LinkPage\Domain\Enums\SocialPosition;
use Modules\LinkPage\Domain\ValueObjects\Appearance;
use Modules\LinkPage\Domain\ValueObjects\LinkUrl;
use Modules\LinkPage\Domain\ValueObjects\ShareImagePath;
use Modules\LinkPage\Domain\ValueObjects\SocialLink;

/**
 * Reads config('modules.settings.link-page'), where the core loads the release defaults
 * deep-merged with the values saved from the panel.
 */
final readonly class LinkPageSettings implements LinkPageSettingsInterface
{
    private const string KEY = 'modules.settings.link-page';

    public function __construct(private SiteSocialProfilesInterface $siteProfiles) {}

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

    public function shareTitle(): ?string
    {
        return $this->shareText('title', self::MAX_SHARE_TITLE_LENGTH);
    }

    public function shareDescription(): ?string
    {
        return $this->shareText('description', self::MAX_SHARE_DESCRIPTION_LENGTH);
    }

    public function shareImage(): ?string
    {
        $image = $this->shareValue('image');

        return is_string($image) && ShareImagePath::isOwn($image) ? $image : null;
    }

    public function noindex(): bool
    {
        return $this->shareValue('noindex') === true;
    }

    public function socialLinks(): array
    {
        $links = [];

        foreach ($this->shownNetworks() as $network) {
            $url = $this->siteProfiles->for($network);

            if ($url instanceof LinkUrl) {
                $links[] = new SocialLink($network, $url);
            }
        }

        return $links;
    }

    public function shownNetworks(): array
    {
        $saved = config(self::KEY.'.social.shown');

        if (! is_array($saved)) {
            return SocialNetwork::cases();
        }

        // In catalogue order, whatever order they were saved in; anything unknown is ignored
        return array_values(array_filter(
            SocialNetwork::cases(),
            static fn (SocialNetwork $network): bool => in_array($network->value, $saved, true),
        ));
    }

    public function socialPosition(): SocialPosition
    {
        $position = config(self::KEY.'.social.position');

        return (is_string($position) ? SocialPosition::tryFrom($position) : null) ?? SocialPosition::Bottom;
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

    private function shareValue(string $key): mixed
    {
        $share = config(self::KEY.'.share');

        return is_array($share) ? ($share[$key] ?? null) : null;
    }

    /** A trimmed text of at most $max characters, or null when there is none. */
    private function shareText(string $key, int $max): ?string
    {
        $text = $this->shareValue($key);

        if (! is_string($text) || trim($text) === '') {
            return null;
        }

        return mb_substr(trim($text), 0, $max);
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
