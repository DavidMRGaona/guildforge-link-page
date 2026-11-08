<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Services;

use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\DTOs\UpcomingEventsCriteriaDTO;
use App\Application\Services\EventQueryServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\DTOs\LinkPageEventDTO;

/**
 * The event buttons of the page, only published core events: the pinned ones that have not
 * ended (soonest first), then the upcoming ones of the horizon and tags, never a hidden one
 * and never more than the limit in all.
 */
final readonly class LinkPageEventsService
{
    public function __construct(
        private EventQueryServiceInterface $events,
        private LinkPageSettingsInterface $settings,
    ) {}

    /**
     * @return list<LinkPageEventDTO>
     */
    public function upcoming(): array
    {
        $limit = $this->settings->eventsLimit();
        $hidden = array_flip($this->settings->hiddenEventIds());

        $pinned = array_slice($this->pinned($hidden), 0, $limit);
        $taken = $hidden + array_flip(array_map(fn (EventResponseDTO $event): string => $event->id, $pinned));

        // Ask for enough to fill the limit after leaving out the hidden and pinned events
        $upcoming = $this->events->getUpcomingEventsWithin(new UpcomingEventsCriteriaDTO(
            limit: $limit + count($taken),
            withinDays: $this->settings->eventsWithinDays(),
            tagSlugs: $this->settings->eventTagSlugs(),
        ));
        $upcoming = array_filter($upcoming, fn (EventResponseDTO $event): bool => ! isset($taken[$event->id]));

        return [
            ...array_map(fn (EventResponseDTO $event): LinkPageEventDTO => $this->toButton($event, pinned: true), $pinned),
            ...array_map(
                fn (EventResponseDTO $event): LinkPageEventDTO => $this->toButton($event, pinned: false),
                array_slice(array_values($upcoming), 0, $limit - count($pinned)),
            ),
        ];
    }

    /**
     * @param  array<string, int>  $hidden
     * @return list<EventResponseDTO>
     */
    private function pinned(array $hidden): array
    {
        $now = CarbonImmutable::now();
        $pinned = [];

        foreach ($this->settings->pinnedEventIds() as $id) {
            // A malformed id would make PostgreSQL refuse the query
            if (isset($hidden[$id]) || ! Str::isUuid($id)) {
                continue;
            }

            $event = $this->events->findById($id);
            if ($event instanceof EventResponseDTO && $event->isPublished && $event->endDate > $now) {
                $pinned[] = $event;
            }
        }

        usort($pinned, fn (EventResponseDTO $a, EventResponseDTO $b): int => $a->startDate <=> $b->startDate);

        return $pinned;
    }

    private function toButton(EventResponseDTO $event, bool $pinned): LinkPageEventDTO
    {
        return new LinkPageEventDTO(
            id: $event->id,
            title: $event->title,
            url: route('events.show', $event->slug),
            startsAt: $event->startDate,
            endsAt: $event->endDate,
            location: $event->location,
            pinned: $pinned,
        );
    }
}
