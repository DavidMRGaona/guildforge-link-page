<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Application;

use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Modules\LinkPage\Application\Contracts\ClockInterface;
use Modules\LinkPage\Application\DTOs\FeaturedEventDTO;
use Modules\LinkPage\Application\Services\FeaturedEventService;
use Modules\LinkPage\Tests\Support\FixedClock;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Which event the big block features: the next published one (with the chosen tag) or the one
 * picked by hand, from the moment the injected clock says. An event in progress is still the
 * featured one until it ends; then the automatic mode moves on and the manual one disappears.
 */
final class FeaturedEventServiceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    private FixedClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        // The venue's wall clock is Madrid; the clock is deliberately not the real "now"
        $this->app->instance(TimezoneServiceInterface::class, new TimezoneService('Europe/Madrid'));
        $this->clock = new FixedClock('2026-11-14T10:00:00Z');
        $this->app->instance(ClockInterface::class, $this->clock);
    }

    public function test_by_default_nothing_is_featured(): void
    {
        $this->event('Jornadas', '2026-11-15 17:00:00');

        $this->assertNull($this->service()->current());
    }

    public function test_automatic_mode_features_the_next_published_event(): void
    {
        $this->auto();
        $this->event('Later', '2026-11-20 17:00:00');
        $this->event('Sooner', '2026-11-15 17:00:00');
        $this->event('Draft', '2026-11-14 17:00:00', published: false);
        $this->event('Past', '2026-11-10 17:00:00');

        $this->assertSame('Sooner', $this->service()->current()?->title);
    }

    public function test_automatic_mode_features_nothing_without_events(): void
    {
        $this->auto();
        $this->event('Past', '2026-11-10 17:00:00');

        $this->assertNull($this->service()->current());
    }

    public function test_an_event_in_progress_is_still_the_featured_one(): void
    {
        $this->auto();
        $this->event('In progress', '2026-11-14 09:00:00', '2026-11-14 12:00:00');
        $this->event('Next', '2026-11-14 15:00:00');

        $this->assertSame('In progress', $this->service()->current()?->title);
    }

    public function test_a_multi_day_event_that_started_days_ago_is_featured_until_it_ends(): void
    {
        $this->auto();
        $this->event('Long convention', '2026-11-12 09:00:00', '2026-11-16 20:00:00');
        $this->event('Next', '2026-11-15 15:00:00');

        $this->assertSame('Long convention', $this->service()->current()?->title);
    }

    public function test_the_featured_event_changes_on_the_second_the_current_one_ends(): void
    {
        $this->auto();
        $this->event('First', '2026-11-14 09:00:00', '2026-11-14 12:00:00');
        $this->event('Second', '2026-11-14 15:00:00');

        $this->clock->set('2026-11-14T11:59:59Z');
        $this->assertSame('First', $this->service()->current()?->title);

        $this->clock->set('2026-11-14T12:00:00Z');
        $this->assertSame('Second', $this->service()->current()?->title);
    }

    public function test_the_next_event_beyond_a_week_is_found_too(): void
    {
        $this->auto();
        $this->event('In three months', '2027-02-10 17:00:00');

        $this->assertSame('In three months', $this->service()->current()?->title);
    }

    public function test_automatic_mode_keeps_to_the_chosen_tag(): void
    {
        $this->auto('jornadas');
        $jornadas = TagModel::factory()->forEvents()->create(['slug' => 'jornadas']);
        $this->event('Untagged', '2026-11-15 17:00:00');
        $this->event('Tagged', '2026-11-18 17:00:00')->tags()->attach($jornadas);

        $this->assertSame('Tagged', $this->service()->current()?->title);
    }

    public function test_a_tag_without_matching_events_features_nothing(): void
    {
        $this->auto('jornadas');
        $this->event('Untagged', '2026-11-15 17:00:00');

        $this->assertNull($this->service()->current());
    }

    public function test_automatic_mode_skips_hidden_events(): void
    {
        $this->auto();
        $hidden = $this->event('Hidden', '2026-11-15 17:00:00');
        $this->event('Shown', '2026-11-16 17:00:00');
        config()->set('modules.settings.link-page.events.hidden', [$hidden->id]);

        $this->assertSame('Shown', $this->service()->current()?->title);
    }

    public function test_manual_mode_features_the_chosen_event_even_if_it_is_not_the_next(): void
    {
        $chosen = $this->event('Chosen', '2026-12-20 17:00:00');
        $this->event('Sooner', '2026-11-15 17:00:00');
        config()->set('modules.settings.link-page.featured', ['mode' => 'manual', 'event' => $chosen->id]);

        $this->assertSame('Chosen', $this->service()->current()?->title);
    }

    public function test_manual_mode_disappears_when_the_event_ends(): void
    {
        $chosen = $this->event('Chosen', '2026-11-14 09:00:00', '2026-11-14 12:00:00');
        $this->event('Next', '2026-11-15 17:00:00');
        config()->set('modules.settings.link-page.featured', ['mode' => 'manual', 'event' => $chosen->id]);

        $this->clock->set('2026-11-14T11:59:59Z');
        $this->assertSame('Chosen', $this->service()->current()?->title);

        $this->clock->set('2026-11-14T12:00:00Z');
        $this->assertNull($this->service()->current());
    }

    public function test_manual_mode_never_features_a_draft_or_a_missing_event(): void
    {
        $draft = $this->event('Draft', '2026-11-15 17:00:00', published: false);

        config()->set('modules.settings.link-page.featured', ['mode' => 'manual', 'event' => $draft->id]);
        $this->assertNull($this->service()->current());

        config()->set('modules.settings.link-page.featured', ['mode' => 'manual', 'event' => '4b0d57f6-6f69-4a91-8d6e-0d9b9d3f3a11']);
        $this->assertNull($this->service()->current());

        config()->set('modules.settings.link-page.featured', ['mode' => 'manual']);
        $this->assertNull($this->service()->current());
    }

    public function test_the_event_carries_what_the_block_shows(): void
    {
        $this->auto();
        $event = $this->event('Jornadas', '2026-11-15 17:00:00', '2026-11-15 21:00:00', location: 'Main hall');

        $featured = $this->service()->current();

        $this->assertInstanceOf(FeaturedEventDTO::class, $featured);
        $this->assertSame($event->id, $featured->id);
        $this->assertSame(route('events.show', $event->slug), $featured->url);
        $this->assertSame('Main hall', $featured->location);
        $this->assertSame(CarbonImmutable::parse('2026-11-15 17:00:00', 'UTC')->getTimestamp(), $featured->startsAt->getTimestamp());
        $this->assertSame(CarbonImmutable::parse('2026-11-15 21:00:00', 'UTC')->getTimestamp(), $featured->endsAt->getTimestamp());
        $this->assertFalse($featured->spansDays);
    }

    public function test_today_starts_at_the_venue_midnight_not_at_utc_midnight(): void
    {
        $this->auto();
        // 18:00 UTC is 19:00 in Madrid on 15 November; its day began at 23:00 UTC the day before
        $this->event('Jornadas', '2026-11-15 18:00:00');

        $this->assertSame('2026-11-14T23:00:00+00:00', $this->service()->current()?->dayStartsAt->setTimezone(new \DateTimeZone('UTC'))->format('c'));
    }

    public function test_today_starts_at_the_right_midnight_on_a_day_of_25_hours(): void
    {
        $this->auto();
        // 25 October 2026: Madrid goes back to winter time; the day began at 22:00 UTC on the 24th
        $this->clock->set('2026-10-20T10:00:00Z');
        $this->event('Late shift', '2026-10-25 12:00:00');

        $this->assertSame('2026-10-24T22:00:00+00:00', $this->service()->current()?->dayStartsAt->setTimezone(new \DateTimeZone('UTC'))->format('c'));
    }

    public function test_today_starts_at_the_right_midnight_on_a_day_of_23_hours(): void
    {
        $this->auto();
        // 29 March 2026: Madrid goes forward to summer time; the day began at 23:00 UTC on the 28th
        $this->clock->set('2026-03-20T10:00:00Z');
        $this->event('Early shift', '2026-03-29 12:00:00');

        $this->assertSame('2026-03-28T23:00:00+00:00', $this->service()->current()?->dayStartsAt->setTimezone(new \DateTimeZone('UTC'))->format('c'));
    }

    public function test_an_event_spans_days_when_it_ends_on_another_venue_day(): void
    {
        $this->auto();
        // Ends at 00:30 in Madrid on the 16th (23:30 UTC on the 15th)
        $this->event('Overnight', '2026-11-15 17:00:00', '2026-11-15 23:30:00');

        $this->assertTrue($this->service()->current()?->spansDays ?? false);
    }

    public function test_an_event_ending_exactly_at_midnight_does_not_span_days(): void
    {
        $this->auto();
        // Ends at 00:00 in Madrid on the 16th (23:00 UTC on the 15th): still the 15th's event
        $this->event('Until midnight', '2026-11-15 17:00:00', '2026-11-15 23:00:00');

        $this->assertFalse($this->service()->current()?->spansDays ?? true);
    }

    public function test_it_carries_the_image_of_the_event(): void
    {
        $this->auto();
        $this->event('Jornadas', '2026-11-15 17:00:00', imagePublicId: 'events/2026/11/cover');

        $this->assertSame('events/2026/11/cover', $this->service()->current()?->imagePublicId);
    }

    public function test_excluding_the_featured_event_from_the_list_leaves_the_others(): void
    {
        $this->auto();
        $featured = $this->event('Featured', '2026-11-15 17:00:00');
        $this->event('Other', '2026-11-16 17:00:00');

        $titles = array_map(
            fn ($event): string => $event->title,
            $this->app->make(\Modules\LinkPage\Application\Services\LinkPageEventsService::class)->upcoming([$featured->id]),
        );

        $this->assertSame(['Other'], $titles);
    }

    private function auto(?string $tag = null): void
    {
        config()->set('modules.settings.link-page.featured', ['mode' => 'auto', 'tag' => $tag]);
    }

    private function service(): FeaturedEventService
    {
        return $this->app->make(FeaturedEventService::class);
    }

    private function event(string $title, string $startsAt, ?string $endsAt = null, bool $published = true, ?string $location = null, ?string $imagePublicId = null): EventModel
    {
        $start = CarbonImmutable::parse($startsAt, 'UTC');

        return EventModel::factory()->create([
            'title' => $title,
            'start_date' => $start,
            'end_date' => $endsAt !== null ? CarbonImmutable::parse($endsAt, 'UTC') : $start->addHours(4),
            'is_published' => $published,
            'location' => $location,
            'image_public_id' => $imagePublicId,
        ]);
    }
}
