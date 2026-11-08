<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\LinkPage\Application\Contracts\ClockInterface;
use Modules\LinkPage\Tests\Support\FixedClock;
use Tests\Support\Modules\ModuleTestCase;

/**
 * What /enlaces sends for the notice and the featured event: the server's own clock as an ISO
 * instant (the countdown counts from it), the notice only inside its window and the featured
 * event left out of the list of buttons.
 */
final class LinkPageHighlightsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    private FixedClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // The list of buttons asks the core for the upcoming events with the application's clock
        $this->travelTo(CarbonImmutable::parse('2026-11-14T10:00:00Z'));
        $this->app->instance(TimezoneServiceInterface::class, new TimezoneService('Europe/Madrid'));
        $this->clock = new FixedClock('2026-11-14T10:00:00Z');
        $this->app->instance(ClockInterface::class, $this->clock);
    }

    public function test_it_sends_the_server_instant_in_the_display_zone(): void
    {
        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('serverNow', '2026-11-14T11:00:00+01:00'));
    }

    public function test_there_is_no_notice_and_no_featured_event_by_default(): void
    {
        $this->get('/enlaces')
            ->assertInertia(fn (Assert $page) => $page->where('notice', null)->where('featured', null));
    }

    public function test_it_sends_the_notice_inside_its_window(): void
    {
        config()->set('modules.settings.link-page.notice', [
            'enabled' => true,
            'text' => 'Closed on Saturday.',
            'tone' => 'warning',
            'link_url' => '/eventos',
            'link_label' => 'See events',
            'starts_at' => '2026-11-14T11:00:00+01:00',
            'ends_at' => '2026-11-14T12:00:00+01:00',
        ]);

        $this->get('/enlaces')
            ->assertInertia(fn (Assert $page) => $page
                ->where('notice.text', 'Closed on Saturday.')
                ->where('notice.tone', 'warning')
                ->where('notice.link.url', '/eventos')
                ->where('notice.link.label', 'See events'));
    }

    public function test_it_sends_no_notice_outside_its_window(): void
    {
        config()->set('modules.settings.link-page.notice', [
            'enabled' => true,
            'text' => 'Closed on Saturday.',
            'tone' => 'warning',
            'starts_at' => '2026-11-14T11:00:01+01:00',
        ]);

        $this->get('/enlaces')->assertInertia(fn (Assert $page) => $page->where('notice', null));
    }

    public function test_a_notice_without_link_sends_a_null_link(): void
    {
        config()->set('modules.settings.link-page.notice', ['enabled' => true, 'text' => 'Hello', 'tone' => 'info']);

        $this->get('/enlaces')->assertInertia(fn (Assert $page) => $page->where('notice.link', null));
    }

    public function test_it_sends_the_featured_event_with_its_instants_in_the_display_zone(): void
    {
        config()->set('modules.settings.link-page.featured', ['mode' => 'auto']);
        $event = $this->event('Jornadas', '2026-11-15 17:00:00', '2026-11-15 21:00:00', 'Main hall');

        $this->get('/enlaces')
            ->assertInertia(fn (Assert $page) => $page
                ->where('featured.id', $event->id)
                ->where('featured.title', 'Jornadas')
                ->where('featured.url', route('events.show', $event->slug))
                ->where('featured.startsAt', '2026-11-15T18:00:00+01:00')
                ->where('featured.endsAt', '2026-11-15T22:00:00+01:00')
                ->where('featured.dayStartsAt', '2026-11-15T00:00:00+01:00')
                ->where('featured.spansDays', false)
                ->where('featured.location', 'Main hall')
                ->where('featured.imagePublicId', null));
    }

    public function test_the_featured_event_is_not_repeated_in_the_list(): void
    {
        config()->set('modules.settings.link-page.featured', ['mode' => 'auto']);
        $this->event('Featured', '2026-11-15 17:00:00');
        $this->event('Other', '2026-11-16 17:00:00');

        $this->get('/enlaces')
            ->assertInertia(fn (Assert $page) => $page
                ->where('featured.title', 'Featured')
                ->has('events', 1)
                ->where('events.0.title', 'Other'));
    }

    public function test_with_the_block_off_the_list_keeps_every_event(): void
    {
        $this->event('First', '2026-11-15 17:00:00');
        $this->event('Second', '2026-11-16 17:00:00');

        $this->get('/enlaces')
            ->assertInertia(fn (Assert $page) => $page->where('featured', null)->has('events', 2));
    }

    public function test_a_partial_reload_of_the_featured_event_follows_the_clock(): void
    {
        config()->set('modules.settings.link-page.featured', ['mode' => 'auto']);
        $this->event('First', '2026-11-14 09:00:00', '2026-11-14 12:00:00');
        $this->event('Second', '2026-11-15 17:00:00');

        $this->get('/enlaces')->assertInertia(fn (Assert $page) => $page->where('featured.title', 'First'));

        $this->clock->set('2026-11-14T12:00:00Z');

        $this->get('/enlaces')->assertInertia(fn (Assert $page) => $page->where('featured.title', 'Second'));
    }

    private function event(string $title, string $startsAt, ?string $endsAt = null, ?string $location = null): EventModel
    {
        $start = CarbonImmutable::parse($startsAt, 'UTC');

        return EventModel::factory()->published()->create([
            'title' => $title,
            'start_date' => $start,
            'end_date' => $endsAt !== null ? CarbonImmutable::parse($endsAt, 'UTC') : $start->addHours(4),
            'location' => $location,
            'image_public_id' => null,
        ]);
    }
}
