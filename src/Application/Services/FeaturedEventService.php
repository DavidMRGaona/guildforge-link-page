<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Services;

use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\DTOs\Response\TagResponseDTO;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use Modules\LinkPage\Application\Contracts\ClockInterface;
use Modules\LinkPage\Application\Contracts\HighlightsSettingsInterface;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\DTOs\FeaturedEventDTO;
use Modules\LinkPage\Domain\Enums\FeaturedMode;
use Modules\LinkPage\Domain\ValueObjects\FeaturedSettings;

/**
 * The event of the big block, only a published core event that has not ended. Automatic mode
 * takes the first one to start (an event in progress still counts, so it holds the block until
 * it ends and then the next one takes over); manual mode takes the chosen one and gives up when
 * it ends. Every day is a day of the display zone and "now" is the injected clock's.
 */
final readonly class FeaturedEventService
{
    /** How far back an event that started earlier can still be in progress. */
    private const int LOOK_BACK_DAYS = 30;

    /** The first query reaches a week ahead; most pages are settled there. */
    private const int NEAR_DAYS = 8;

    /** Display days ahead the automatic mode looks for the next event. */
    private const int HORIZON_DAYS = 366;

    public function __construct(
        private EventQueryServiceInterface $events,
        private HighlightsSettingsInterface $highlights,
        private LinkPageSettingsInterface $settings,
        private TimezoneServiceInterface $timezone,
        private ClockInterface $clock,
    ) {}

    public function current(): ?FeaturedEventDTO
    {
        $featured = $this->highlights->featured();
        $now = $this->clock->now();

        $event = match ($featured->mode) {
            FeaturedMode::Off => null,
            FeaturedMode::Manual => $this->chosen($featured, $now),
            FeaturedMode::Auto => $this->next($featured, $now),
        };

        return $event instanceof EventResponseDTO ? $this->toFeatured($event) : null;
    }

    private function chosen(FeaturedSettings $featured, DateTimeImmutable $now): ?EventResponseDTO
    {
        if ($featured->eventId === null) {
            return null;
        }

        $event = $this->events->findById($featured->eventId);

        return $event instanceof EventResponseDTO && $event->isPublished && $event->endDate > $now ? $event : null;
    }

    private function next(FeaturedSettings $featured, DateTimeImmutable $now): ?EventResponseDTO
    {
        $today = $this->timezone->toDisplay($now)->setTime(0, 0);
        $hidden = array_flip($this->settings->hiddenEventIds());

        $windows = [
            [$today->modify('-'.self::LOOK_BACK_DAYS.' days'), $today->modify('+'.self::NEAR_DAYS.' days')],
            [$today->modify('+'.self::NEAR_DAYS.' days'), $today->modify('+'.self::HORIZON_DAYS.' days')],
        ];

        foreach ($windows as [$start, $end]) {
            $candidates = array_values(array_filter(
                $this->events->findByDateRange($start->format('Y-m-d'), $end->format('Y-m-d')),
                fn (EventResponseDTO $event): bool => $event->isPublished
                    && $event->endDate > $now
                    && ! isset($hidden[$event->id])
                    && ($featured->tagSlug === null || $this->hasTag($event, $featured->tagSlug)),
            ));

            if ($candidates !== []) {
                usort($candidates, fn (EventResponseDTO $a, EventResponseDTO $b): int => $a->startDate <=> $b->startDate);

                return $candidates[0];
            }
        }

        return null;
    }

    private function hasTag(EventResponseDTO $event, string $slug): bool
    {
        return array_any($event->tags, fn (TagResponseDTO $tag): bool => $tag->slug === $slug);
    }

    private function toFeatured(EventResponseDTO $event): FeaturedEventDTO
    {
        [$dayStart, $dayEnd] = $this->timezone->displayDayRange($event->startDate);

        return new FeaturedEventDTO(
            id: $event->id,
            title: $event->title,
            url: route('events.show', $event->slug),
            startsAt: $event->startDate,
            endsAt: $event->endDate,
            dayStartsAt: $dayStart,
            spansDays: $event->endDate > $dayEnd,
            location: $event->location,
            imagePublicId: $event->imagePublicId,
        );
    }
}
