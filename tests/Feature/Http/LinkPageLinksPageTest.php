<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\LinkPage\Tests\Support\CreatesLinks;
use Tests\Support\Modules\ModuleTestCase;

final class LinkPageLinksPageTest extends ModuleTestCase
{
    use CreatesLinks;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->useUtcStorage();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));
    }

    public function test_without_links_or_networks_the_page_has_empty_lists(): void
    {
        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('links.before', [])
                ->where('links.after', [])
                ->where('social.links', [])
                ->where('social.position', 'bottom')
            );
    }

    public function test_links_are_split_around_the_events_and_keep_their_order(): void
    {
        $a = $this->link(['title' => 'A', 'placement' => 'before', 'sort_order' => 1]);
        $b = $this->link(['title' => 'B', 'placement' => 'after', 'sort_order' => 2]);
        $c = $this->link([
            'title' => 'C',
            'url' => 'mailto:info@example.test',
            'description' => 'Write to us',
            'icon' => 'email',
            'placement' => 'before',
            'is_featured' => true,
            'sort_order' => 3,
        ]);

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('links.before', 2)
                ->where('links.before.0', [
                    'linkId' => 'link:'.$a->id,
                    'title' => 'A',
                    'url' => 'https://discord.gg/example',
                    'description' => null,
                    'icon' => null,
                    'featured' => false,
                    'external' => true,
                ])
                ->where('links.before.1', [
                    'linkId' => 'link:'.$c->id,
                    'title' => 'C',
                    'url' => 'mailto:info@example.test',
                    'description' => 'Write to us',
                    'icon' => 'email',
                    'featured' => true,
                    'external' => false,
                ])
                ->has('links.after', 1)
                ->where('links.after.0.linkId', 'link:'.$b->id)
            );
    }

    public function test_links_outside_their_dates_or_switched_off_do_not_reach_the_page(): void
    {
        $this->link(['title' => 'Off', 'is_active' => false]);
        $this->link(['title' => 'Later', 'starts_at' => CarbonImmutable::parse('2026-06-16 00:00:00', 'UTC')]);
        $this->link(['title' => 'Gone', 'ends_at' => CarbonImmutable::parse('2026-06-14 00:00:00', 'UTC')]);
        $this->link(['title' => 'Now', 'starts_at' => CarbonImmutable::parse('2026-06-15 09:00:00', 'UTC')]);

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('links.before', 1)
                ->where('links.before.0.title', 'Now')
            );
    }

    public function test_a_stored_unsafe_address_is_not_sent_to_the_page(): void
    {
        $link = $this->link(['title' => 'Tampered']);
        DB::table('link_page_links')->where('id', $link->id)->update(['url' => 'JavaScript:alert(1)']);

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('links.before', []))
            ->assertDontSee('alert(1)', false);
    }

    public function test_the_social_row_shows_the_sites_own_profiles_with_a_name_icon_and_stable_id(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('social_discord', 'discord.gg/example');
        $settings->set('social_instagram', 'javascript:alert(1)');
        $settings->set('contact_phone', '+34 600 123 456');
        config()->set('modules.settings.link-page.social.position', 'top');

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('social.position', 'top')
                ->has('social.links', 2)
                ->where('social.links.0', [
                    'linkId' => 'social:discord',
                    'network' => 'discord',
                    'label' => 'Discord',
                    'url' => 'https://discord.gg/example',
                    'icon' => 'discord',
                    'external' => true,
                ])
                ->where('social.links.1.linkId', 'social:phone')
                ->where('social.links.1.url', 'tel:+34600123456')
                ->where('social.links.1.external', false)
            );
    }

    public function test_a_network_unticked_on_the_page_is_not_shown_even_if_the_site_has_it(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('social_discord', 'discord.gg/example');
        $settings->set('social_instagram', 'www.instagram.com/example');
        config()->set('modules.settings.link-page.social.shown', ['instagram']);

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('social.links', 1)
                ->where('social.links.0.linkId', 'social:instagram')
            );
    }

    public function test_every_event_button_has_a_stable_id_too(): void
    {
        $event = EventModel::factory()->published()->create([
            'title' => 'Campaign night',
            'start_date' => CarbonImmutable::parse('2026-06-16 18:00:00', 'UTC'),
            'end_date' => CarbonImmutable::parse('2026-06-16 23:00:00', 'UTC'),
        ]);

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('events.0.linkId', 'event:'.$event->id));
    }
}
