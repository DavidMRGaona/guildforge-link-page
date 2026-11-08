<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Application;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Carbon\CarbonImmutable;
use Modules\LinkPage\Application\DTOs\LinkPageEventDTO;
use Modules\LinkPage\Application\Services\LinkPageEventsService;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Which core events the page lists as buttons: the pinned ones first, then the upcoming
 * ones of the horizon and tags, never a hidden one, never more than the limit.
 */
final class LinkPageEventsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));
    }

    public function test_it_lists_the_upcoming_published_events_soonest_first(): void
    {
        $this->event('Later', '2026-06-20 18:00:00');
        $this->event('Sooner', '2026-06-16 18:00:00');
        $this->event('Draft', '2026-06-17 18:00:00', published: false);
        $this->event('Past', '2026-06-10 18:00:00');

        $this->assertSame(['Sooner', 'Later'], $this->titles());
    }

    public function test_it_keeps_to_the_horizon_and_the_limit(): void
    {
        config()->set('modules.settings.link-page.events', ['limit' => 2, 'within_days' => 7]);

        $this->event('First', '2026-06-16 18:00:00');
        $this->event('Second', '2026-06-17 18:00:00');
        $this->event('Third', '2026-06-18 18:00:00');
        $this->event('Beyond the horizon', '2026-07-30 18:00:00');

        $this->assertSame(['First', 'Second'], $this->titles());
    }

    public function test_it_keeps_the_events_with_any_of_the_tags(): void
    {
        config()->set('modules.settings.link-page.events.tags', ['wargames']);

        $wargames = TagModel::factory()->forEvents()->create(['slug' => 'wargames']);
        $this->event('Skirmish', '2026-06-16 18:00:00')->tags()->attach($wargames);
        $this->event('Untagged', '2026-06-17 18:00:00');

        $this->assertSame(['Skirmish'], $this->titles());
    }

    public function test_pinned_events_go_first_and_count_within_the_limit(): void
    {
        $pinned = $this->event('Pinned far away', '2026-12-01 18:00:00');
        $this->event('First', '2026-06-16 18:00:00');
        $this->event('Second', '2026-06-17 18:00:00');
        config()->set('modules.settings.link-page.events', ['limit' => 2, 'pinned' => [$pinned->id]]);

        $events = $this->service()->upcoming();

        $this->assertSame(['Pinned far away', 'First'], array_map(fn (LinkPageEventDTO $event): string => $event->title, $events));
        $this->assertTrue($events[0]->pinned);
        $this->assertFalse($events[1]->pinned);
    }

    public function test_a_pinned_event_is_not_repeated_among_the_upcoming_ones(): void
    {
        $pinned = $this->event('Pinned', '2026-06-17 18:00:00');
        $this->event('Other', '2026-06-16 18:00:00');
        config()->set('modules.settings.link-page.events.pinned', [$pinned->id]);

        $this->assertSame(['Pinned', 'Other'], $this->titles());
    }

    public function test_a_pinned_event_stays_while_it_runs_and_goes_once_it_ends(): void
    {
        $running = $this->event('Running', '2026-06-15 09:00:00', '2026-06-15 20:00:00');
        $ended = $this->event('Ended', '2026-06-14 09:00:00', '2026-06-14 20:00:00');
        $draft = $this->event('Draft', '2026-06-20 09:00:00', published: false);
        config()->set('modules.settings.link-page.events.pinned', [$ended->id, $draft->id, $running->id, 'not-an-event']);

        $this->assertSame(['Running'], $this->titles());
    }

    public function test_more_pinned_events_than_the_limit_keep_the_soonest(): void
    {
        $later = $this->event('Later', '2026-06-20 18:00:00');
        $sooner = $this->event('Sooner', '2026-06-16 18:00:00');
        config()->set('modules.settings.link-page.events', ['limit' => 1, 'pinned' => [$later->id, $sooner->id]]);

        $this->assertSame(['Sooner'], $this->titles());
    }

    public function test_hidden_events_never_show_even_when_pinned(): void
    {
        $hidden = $this->event('Hidden', '2026-06-16 18:00:00');
        $hiddenAndPinned = $this->event('Hidden and pinned', '2026-06-17 18:00:00');
        $this->event('Shown', '2026-06-18 18:00:00');
        config()->set('modules.settings.link-page.events', [
            'limit' => 1,
            'pinned' => [$hiddenAndPinned->id],
            'hidden' => [$hidden->id, $hiddenAndPinned->id],
        ]);

        // The limit is still filled although the soonest event is hidden
        $this->assertSame(['Shown'], $this->titles());
    }

    public function test_each_event_carries_its_page_and_its_dates(): void
    {
        $event = $this->event('Campaign night', '2026-06-16 18:00:00', '2026-06-16 23:00:00');

        $dto = $this->service()->upcoming()[0];

        $this->assertSame($event->id, $dto->id);
        $this->assertSame(route('events.show', $event->slug), $dto->url);
        $this->assertSame(CarbonImmutable::parse('2026-06-16 18:00:00', 'UTC')->getTimestamp(), $dto->startsAt->getTimestamp());
        $this->assertSame(CarbonImmutable::parse('2026-06-16 23:00:00', 'UTC')->getTimestamp(), $dto->endsAt->getTimestamp());
    }

    /**
     * @return list<string>
     */
    private function titles(): array
    {
        return array_map(fn (LinkPageEventDTO $event): string => $event->title, $this->service()->upcoming());
    }

    private function service(): LinkPageEventsService
    {
        return $this->app->make(LinkPageEventsService::class);
    }

    private function event(string $title, string $startsAt, ?string $endsAt = null, bool $published = true): EventModel
    {
        $start = CarbonImmutable::parse($startsAt, 'UTC');

        return EventModel::factory()->create([
            'title' => $title,
            'start_date' => $start,
            'end_date' => $endsAt !== null ? CarbonImmutable::parse($endsAt, 'UTC') : $start->addHours(4),
            'is_published' => $published,
        ]);
    }
}
