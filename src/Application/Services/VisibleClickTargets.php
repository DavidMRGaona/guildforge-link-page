<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Services;

use Modules\LinkPage\Application\Contracts\CalendarClickTargetsInterface;
use Modules\LinkPage\Application\Contracts\LinkPageLinksInterface;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\DTOs\LinkPageEventDTO;
use Modules\LinkPage\Application\DTOs\LinkPageLinkDTO;
use Modules\LinkPage\Domain\Enums\ClickTargetType;
use Modules\LinkPage\Domain\ValueObjects\ClickTarget;
use Modules\LinkPage\Domain\ValueObjects\SocialLink;

/**
 * What a click on the page may count: only what the page shows right now. An id the page does
 * not show (a link switched off, a network without address, an event no longer listed) or an
 * address that is no entry of its calendar counts nothing.
 */
final readonly class VisibleClickTargets
{
    /** The id the page sends for an entry of its calendar, together with the entry's address. */
    public const string CALENDAR = 'calendar';

    public function __construct(
        private LinkPageLinksInterface $links,
        private LinkPageSettingsInterface $settings,
        private LinkPageEventsService $events,
        private CalendarClickTargetsInterface $calendar,
    ) {}

    public function resolve(string $id, ?string $url = null): ?ClickTarget
    {
        if ($id === self::CALENDAR) {
            return $url === null ? null : $this->calendar->forUrl($url);
        }

        $target = ClickTarget::tryFrom($id);
        if ($target === null) {
            return null;
        }

        $shown = match ($target->type) {
            ClickTargetType::Home => true,
            ClickTargetType::Link => in_array($target->value(), array_map(fn (LinkPageLinkDTO $link): string => $link->linkId(), $this->links->visible()), true),
            ClickTargetType::Social => in_array($target->value(), array_map(fn (SocialLink $link): string => $link->linkId(), $this->settings->socialLinks()), true),
            ClickTargetType::Event => in_array($target->reference, array_map(fn (LinkPageEventDTO $event): string => strtolower($event->id), $this->events->upcoming()), true),
            // Calendar entries are only known by their address
            ClickTargetType::Calendar => false,
        };

        return $shown ? $target : null;
    }
}
