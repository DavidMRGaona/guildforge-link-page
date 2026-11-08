<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Modules\ModuleTestCase;

final class LinkPageTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));
    }

    public function test_guests_see_the_page_with_the_site_name_and_the_upcoming_events(): void
    {
        app(SettingsServiceInterface::class)->set('guild_name', 'Example guild');
        config()->set('modules.settings.link-page.intro', 'Scan, play, repeat.');
        $event = EventModel::factory()->published()->create([
            'title' => 'Campaign night',
            'start_date' => CarbonImmutable::parse('2026-06-16 18:00:00', 'UTC'),
            'end_date' => CarbonImmutable::parse('2026-06-16 23:00:00', 'UTC'),
        ]);

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LinkPage/Show', shouldExist: false)
                ->where('site.name', 'Example guild')
                ->where('intro', 'Scan, play, repeat.')
                ->has('events', 1)
                ->where('events.0.id', $event->id)
                ->where('events.0.title', 'Campaign night')
                ->where('events.0.url', route('events.show', $event->slug))
                ->where('events.0.pinned', false)
                ->has('events.0.startsAt')
                ->has('events.0.endsAt')
                ->where('calendar.initialView', 'list')
                ->where('calendar.views', ['list', 'month'])
                ->where('homeUrl', url('/'))
                ->where('seo.title', fn (string $title): bool => str_contains($title, 'Example guild'))
            );
    }

    public function test_the_page_has_no_site_layout(): void
    {
        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('coreLayoutPagePrefixes', fn (iterable $prefixes): bool => ! in_array('LinkPage', [...$prefixes], true))
            );
    }

    public function test_the_initial_calendar_view_comes_from_the_settings(): void
    {
        config()->set('modules.settings.link-page.calendar.initial_view', 'month');

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('calendar.initialView', 'month'));
    }

    public function test_a_disabled_page_is_not_found(): void
    {
        config()->set('modules.settings.link-page.enabled', false);

        $this->get('/enlaces')->assertNotFound();
    }

    public function test_the_route_is_offered_to_the_menu(): void
    {
        $this->assertSame(url('/enlaces'), route('link_page.show'));
    }
}
