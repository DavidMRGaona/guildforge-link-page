<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament;

use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Services\TimezoneService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\LinkPage\Application\Contracts\HighlightsSettingsInterface;
use Modules\LinkPage\Domain\Enums\FeaturedMode;
use Modules\LinkPage\Domain\Enums\NoticeTone;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The notice and featured event sections of the link page settings. The pickers speak the
 * storage zone (the panel shows them in the display zone); the notice is stored as ISO 8601
 * instants.
 */
final class LinkPageHighlightsFormTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsServiceInterface::class)->set('guild_name', 'Example guild');
        $this->app->instance(TimezoneServiceInterface::class, new TimezoneService('Europe/Madrid'));
        $this->registerOnAdminPanel([], [LinkPageSettingsPage::class]);
        $this->actingAsPanelAdmin();
    }

    public function test_the_form_opens_with_no_notice_and_nothing_featured(): void
    {
        $this->page()->assertFormSet([
            'notice.enabled' => false,
            'notice.text' => null,
            'notice.tone' => 'info',
            'notice.link_url' => null,
            'notice.starts_at' => null,
            'notice.ends_at' => null,
            'featured.mode' => 'off',
            'featured.tag' => null,
            'featured.event' => null,
        ]);
    }

    public function test_it_saves_a_notice_with_its_window_in_the_display_zone(): void
    {
        $this->page()
            ->fillForm([
                'notice.enabled' => true,
                'notice.text' => '  Closed on Saturday.  ',
                'notice.tone' => 'warning',
                'notice.link_url' => 'https://example.com/news',
                'notice.link_label' => 'Read more',
                // What an editor types is the venue's wall time: 10:00 in Madrid is 09:00 UTC
                'notice.starts_at' => '2026-11-10 10:00:00',
                'notice.ends_at' => '2026-11-12 10:00:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();
        $notice = $this->settings()->notice();

        $this->assertNotNull($notice);
        $this->assertSame('Closed on Saturday.', $notice->text);
        $this->assertSame(NoticeTone::Warning, $notice->tone);
        $link = $notice->link;
        $this->assertNotNull($link);
        $this->assertSame('https://example.com/news', $link->url);
        $this->assertSame('Read more', $link->label);
        $this->assertEquals(new DateTimeImmutable('2026-11-10T09:00:00Z'), $notice->startsAt);
        $this->assertEquals(new DateTimeImmutable('2026-11-12T09:00:00Z'), $notice->endsAt);
        // Stored in the display zone, so what is read in the database matches what is typed
        $this->assertSame('2026-11-10T10:00:00+01:00', $notice->toArray()['starts_at']);
    }

    public function test_the_form_shows_the_stored_window_back(): void
    {
        config()->set('modules.settings.link-page.notice', [
            'enabled' => true,
            'text' => 'Hello',
            'tone' => 'important',
            'starts_at' => '2026-11-10T10:00:00+01:00',
            'ends_at' => '2026-11-12T10:00:00+01:00',
        ]);

        $page = $this->page();

        // assertFormSet reads through the picker's cast, which answers in the storage zone
        $page->assertFormSet([
            'notice.enabled' => true,
            'notice.tone' => 'important',
            'notice.starts_at' => '2026-11-10 09:00',
            'notice.ends_at' => '2026-11-12 09:00',
        ]);
        // What the picker holds, and shows, is the venue's wall time
        $this->assertSame('2026-11-10 10:00:00', $page->get('data.notice.starts_at'));
        $this->assertSame('2026-11-12 10:00:00', $page->get('data.notice.ends_at'));
    }

    public function test_the_dates_are_optional(): void
    {
        $this->page()
            ->fillForm(['notice.enabled' => true, 'notice.text' => 'Always on', 'notice.starts_at' => null, 'notice.ends_at' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();

        $this->assertNull($this->settings()->notice()?->startsAt);
        $this->assertNull($this->settings()->notice()?->endsAt);
    }

    public function test_an_enabled_notice_needs_a_text(): void
    {
        $this->page()
            ->fillForm(['notice.enabled' => true, 'notice.text' => '   '])
            ->call('save')
            ->assertHasFormErrors(['notice.text']);
    }

    public function test_a_disabled_notice_keeps_its_text_without_asking_for_one(): void
    {
        $this->page()
            ->fillForm(['notice.enabled' => false, 'notice.text' => 'Draft text', 'notice.tone' => 'important'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();

        $form = $this->app->make(HighlightsSettingsInterface::class)->noticeForForm();
        $this->assertFalse($form['enabled']);
        $this->assertSame('Draft text', $form['text']);
        $this->assertNull($this->settings()->notice());
    }

    public function test_the_text_has_a_limit(): void
    {
        $this->page()
            ->fillForm(['notice.enabled' => true, 'notice.text' => str_repeat('a', 161)])
            ->call('save')
            ->assertHasFormErrors(['notice.text']);
    }

    public function test_a_link_that_is_not_https_or_a_site_path_is_rejected(): void
    {
        foreach (['javascript:alert(1)', 'http://example.com', '//evil.example.com', 'example.com'] as $url) {
            $this->page()
                ->fillForm(['notice.enabled' => true, 'notice.text' => 'Hello', 'notice.link_url' => $url])
                ->call('save')
                ->assertHasFormErrors(['notice.link_url']);
        }
    }

    public function test_a_site_path_is_a_valid_link(): void
    {
        $this->page()
            ->fillForm(['notice.enabled' => true, 'notice.text' => 'Hello', 'notice.link_url' => '/eventos'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_the_label_is_dropped_without_a_link(): void
    {
        $this->page()
            ->fillForm(['notice.enabled' => true, 'notice.text' => 'Hello', 'notice.link_url' => null, 'notice.link_label' => 'Orphan'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();

        $this->assertNull($this->settings()->noticeForForm()['link_label']);
    }

    public function test_the_end_must_be_after_the_start(): void
    {
        $this->page()
            ->fillForm(['notice.enabled' => true, 'notice.text' => 'Hello', 'notice.starts_at' => '2026-11-12 10:00:00', 'notice.ends_at' => '2026-11-12 10:00:00'])
            ->call('save')
            ->assertHasFormErrors(['notice.ends_at']);
    }

    public function test_it_saves_the_automatic_mode_with_a_tag(): void
    {
        TagModel::factory()->forEvents()->create(['slug' => 'jornadas']);

        $this->page()
            ->fillForm(['featured.mode' => 'auto', 'featured.tag' => 'jornadas'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();
        $featured = $this->settings()->featured();

        $this->assertSame(FeaturedMode::Auto, $featured->mode);
        $this->assertSame('jornadas', $featured->tagSlug);
        $this->assertNull($featured->eventId);
    }

    public function test_it_saves_the_manual_mode_with_a_chosen_event(): void
    {
        $event = $this->futureEvent();

        $this->page()
            ->fillForm(['featured.mode' => 'manual', 'featured.event' => $event->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();
        $featured = $this->settings()->featured();

        $this->assertSame(FeaturedMode::Manual, $featured->mode);
        $this->assertSame($event->id, $featured->eventId);
        $this->assertNull($featured->tagSlug);
    }

    public function test_the_manual_mode_needs_an_event(): void
    {
        $this->page()
            ->fillForm(['featured.mode' => 'manual', 'featured.event' => null])
            ->call('save')
            ->assertHasFormErrors(['featured.event']);
    }

    public function test_the_off_mode_forgets_the_tag_and_the_event(): void
    {
        TagModel::factory()->forEvents()->create(['slug' => 'jornadas']);

        $this->page()
            ->fillForm(['featured.mode' => 'off', 'featured.tag' => 'jornadas'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();

        $this->assertNull($this->settings()->featured()->tagSlug);
    }

    public function test_a_forged_tag_or_event_is_rejected(): void
    {
        $this->page()
            ->fillForm(['featured.mode' => 'auto', 'featured.tag' => 'does-not-exist'])
            ->call('save')
            ->assertHasFormErrors(['featured.tag']);

        $this->page()
            ->fillForm(['featured.mode' => 'manual', 'featured.event' => '4b0d57f6-6f69-4a91-8d6e-0d9b9d3f3a11'])
            ->call('save')
            ->assertHasFormErrors(['featured.event']);
    }

    public function test_a_chosen_event_that_has_ended_is_not_an_option(): void
    {
        $ended = EventModel::factory()->published()->create([
            'start_date' => CarbonImmutable::now()->subDays(3),
            'end_date' => CarbonImmutable::now()->subDays(2),
        ]);

        $this->page()
            ->fillForm(['featured.mode' => 'manual', 'featured.event' => $ended->id])
            ->call('save')
            ->assertHasFormErrors(['featured.event']);
    }

    private function futureEvent(): EventModel
    {
        return EventModel::factory()->published()->create([
            'start_date' => CarbonImmutable::now()->addDays(10),
            'end_date' => CarbonImmutable::now()->addDays(10)->addHours(3),
        ]);
    }

    private function settings(): HighlightsSettingsInterface
    {
        return $this->app->make(HighlightsSettingsInterface::class);
    }

    /**
     * @return Testable<LinkPageSettingsPage>
     */
    private function page(): Testable
    {
        return Livewire::test(LinkPageSettingsPage::class);
    }

    /** What the core does after saving: the effective settings come from the store again. */
    private function refreshModuleSettings(): void
    {
        $stored = app(ModuleSettingsStoreInterface::class)->stored('link-page');
        config()->set('modules.settings.link-page', array_replace_recursive(require __DIR__.'/../../../config/settings.php', $stored));
    }
}
