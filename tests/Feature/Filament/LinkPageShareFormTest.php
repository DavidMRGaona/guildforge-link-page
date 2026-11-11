<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament;

use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Services\SettingsServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Modules\LinkPage\Tests\Support\InteractsWithMediaLibrary;
use Modules\LinkPage\Tests\Support\RecordingMediaFileReleaser;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The share section of the link page settings: the card title, description and image, and the
 * option that keeps search engines out. Images go to the images disk and the replaced one is deleted.
 */
final class LinkPageShareFormTest extends ModuleTestCase
{
    use InteractsWithMediaLibrary;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        app(SettingsServiceInterface::class)->set('guild_name', 'Example guild');
        $this->registerOnAdminPanel([], [LinkPageSettingsPage::class]);
        $this->actingAsPanelAdmin();
    }

    public function test_the_form_opens_with_the_default_card(): void
    {
        $this->page()->assertFormSet([
            'share.title' => null,
            'share.description' => null,
            'share.image' => null,
            'share.noindex' => false,
        ]);
    }

    public function test_it_saves_the_card_texts_and_the_noindex_option(): void
    {
        $this->page()
            ->fillForm([
                'share.title' => '  Join the guild  ',
                'share.description' => 'Games every week.',
                'share.noindex' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = $this->settings();
        $this->assertSame('Join the guild', $settings->shareTitle());
        $this->assertSame('Games every week.', $settings->shareDescription());
        $this->assertTrue($settings->noindex());
    }

    public function test_empty_fields_go_back_to_the_site_card(): void
    {
        $this->page()
            ->fillForm(['share.title' => 'Join the guild', 'share.description' => 'Games every week.'])
            ->call('save');

        $this->refreshModuleSettings();

        $this->page()
            ->fillForm(['share.title' => '', 'share.description' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($this->settings()->shareTitle());
        $this->assertNull($this->settings()->shareDescription());
    }

    public function test_the_texts_have_a_limit(): void
    {
        $this->page()
            ->fillForm([
                'share.title' => str_repeat('t', LinkPageSettingsInterface::MAX_SHARE_TITLE_LENGTH + 1),
                'share.description' => str_repeat('d', LinkPageSettingsInterface::MAX_SHARE_DESCRIPTION_LENGTH + 1),
            ])
            ->call('save')
            ->assertHasFormErrors(['share.title', 'share.description']);
    }

    public function test_saving_the_card_keeps_the_rest_of_the_settings(): void
    {
        app(ModuleSettingsStoreInterface::class)->store('link-page', ['intro' => 'Scan, play, repeat.']);
        $this->refreshModuleSettings();

        $this->page()
            ->fillForm(['share.title' => 'Join the guild'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();
        $this->assertSame('Scan, play, repeat.', $this->settings()->intro());
        $this->assertSame('Join the guild', $this->settings()->shareTitle());
    }

    public function test_an_uploaded_image_is_stored_on_the_images_disk_and_a_new_one_replaces_it(): void
    {
        $releaser = RecordingMediaFileReleaser::bind();
        $this->page()
            ->fillForm(['share.image' => UploadedFile::fake()->image('card.jpg', 1200, 630)])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();
        $first = $this->settings()->shareImage();
        $this->assertNotNull($first);
        $this->assertStringStartsWith('link-page/share/', $first);
        $this->assertMatchesRegularExpression('#^link-page/share/\d{4}/\d{2}/[0-9a-f-]{36}\.jpg$#', $first);
        Storage::disk('images')->assertExists($first);

        // A single upload replaces its file in the browser; the test helper would add it beside
        $this->page()
            ->set('data.share.image', [])
            ->fillForm(['share.image' => UploadedFile::fake()->image('other.png', 1200, 630)])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();
        $second = $this->settings()->shareImage();
        $this->assertNotNull($second);
        $this->assertNotSame($first, $second);
        $this->assertStringEndsWith('.png', $second);
        Storage::disk('images')->assertExists($second);
        // Another record may have chosen the replaced file: the core checks before deleting it
        $this->assertSame([$first], $releaser->released);
        Storage::disk('images')->assertExists($first);
    }

    public function test_removing_the_image_releases_the_file_through_the_core(): void
    {
        $releaser = RecordingMediaFileReleaser::bind();
        Storage::disk('images')->put('link-page/share/2026/11/card.jpg', 'image');
        app(ModuleSettingsStoreInterface::class)->store('link-page', ['share' => ['image' => 'link-page/share/2026/11/card.jpg']]);
        $this->refreshModuleSettings();

        $this->page()
            ->set('data.share.image', [])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();
        $this->assertNull($this->settings()->shareImage());
        $this->assertSame(['link-page/share/2026/11/card.jpg'], $releaser->released);
    }

    public function test_saving_without_changing_the_image_releases_nothing(): void
    {
        $releaser = RecordingMediaFileReleaser::bind();
        Storage::disk('images')->put('link-page/share/2026/11/card.jpg', 'image');
        app(ModuleSettingsStoreInterface::class)->store('link-page', ['share' => ['image' => 'link-page/share/2026/11/card.jpg']]);
        $this->refreshModuleSettings();

        $this->page()
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();
        $this->assertSame('link-page/share/2026/11/card.jpg', $this->settings()->shareImage());
        $this->assertSame([], $releaser->released);
    }

    public function test_the_image_field_offers_the_library(): void
    {
        $this->assertFieldOffersLibrary($this->page(), 'share.image');
    }

    public function test_an_image_of_the_library_can_be_chosen_for_the_card(): void
    {
        $releaser = RecordingMediaFileReleaser::bind();
        $this->indexLibraryFile('events/2026/11/poster', 'jpg');
        app(ModuleSettingsStoreInterface::class)->store('link-page', ['share' => ['image' => 'link-page/share/2026/11/card.jpg']]);
        $this->refreshModuleSettings();

        $this->page()
            ->set('data.share.image', ['chosen' => 'events/2026/11/poster.jpg'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->refreshModuleSettings();
        $this->assertSame('events/2026/11/poster.jpg', $this->settings()->shareImage());
        $this->assertSame(['link-page/share/2026/11/card.jpg'], $releaser->released);
    }

    public function test_a_library_image_the_card_does_not_take_is_refused(): void
    {
        $this->indexLibraryFile('events/2026/11/poster', 'webp');
        $this->indexLibraryFile('events/2026/11/big', 'jpg', bytes: 1025 * 1024);

        foreach (['events/2026/11/poster.webp', 'events/2026/11/big.jpg'] as $path) {
            $this->page()
                ->set('data.share.image', ['chosen' => $path])
                ->call('save')
                ->assertHasFormErrors(['share.image']);
        }

        $this->refreshModuleSettings();
        $this->assertNull($this->settings()->shareImage());
    }

    public function test_a_library_image_whose_name_the_card_cannot_use_says_so_on_the_field(): void
    {
        $this->indexLibraryFile('events/2026/11/my poster', 'jpg');

        $this->page()
            ->set('data.share.image', ['chosen' => 'events/2026/11/my poster.jpg'])
            ->call('save')
            ->assertHasFormErrors(['share.image']);

        $this->refreshModuleSettings();
        $this->assertNull($this->settings()->shareImage());
    }

    public function test_the_image_takes_only_jpg_and_png_of_up_to_one_megabyte(): void
    {
        $this->page()
            ->fillForm(['share.image' => UploadedFile::fake()->image('card.webp', 1200, 630)])
            ->call('save')
            ->assertHasFormErrors(['share.image']);

        $this->page()
            ->fillForm(['share.image' => UploadedFile::fake()->image('card.jpg', 1200, 630)->size(1025)])
            ->call('save')
            ->assertHasFormErrors(['share.image']);

        $this->assertNull($this->settings()->shareImage());
    }

    public function test_a_forged_path_is_refused_and_the_stored_card_kept(): void
    {
        $releaser = RecordingMediaFileReleaser::bind();
        Storage::disk('images')->put('events/2026/11/poster.jpg', 'poster');
        app(ModuleSettingsStoreInterface::class)->store('link-page', ['share' => ['image' => 'link-page/share/2026/11/card.jpg']]);
        $this->refreshModuleSettings();

        // A forged form state points the card at a file that is not in the library
        $this->page()
            ->set('data.share.image', ['forged' => 'events/2026/11/poster.jpg'])
            ->call('save')
            ->assertHasFormErrors(['share.image']);

        $this->refreshModuleSettings();
        $this->assertSame('link-page/share/2026/11/card.jpg', $this->settings()->shareImage());
        $this->assertSame([], $releaser->released);
        Storage::disk('images')->assertExists('events/2026/11/poster.jpg');
    }

    public function test_the_preview_shows_the_card_as_it_will_read(): void
    {
        $this->page()
            ->assertSeeHtml('data-share-preview')
            ->assertSee('Example guild')
            ->fillForm(['share.title' => 'Join the guild', 'share.description' => 'Games every week.'])
            ->assertSee('Join the guild')
            ->assertSee('Games every week.');
    }

    public function test_the_preview_falls_back_to_the_intro_before_it_is_saved(): void
    {
        $this->page()
            ->fillForm(['intro' => 'Scan, play, repeat.'])
            ->assertSee('Scan, play, repeat.');
    }

    public function test_the_preview_shows_the_image_the_card_falls_back_to(): void
    {
        $this->page()->assertSeeHtml(url('/images/defaults/logo-light.png'));

        app(SettingsServiceInterface::class)->set('site_logo_light', 'settings/logo.png');
        config(['cloudinary.cloud_name' => 'demo', 'cloudinary.prefix' => 'guildforge']);

        $this->page()->assertSeeHtml('https://res.cloudinary.com/demo/image/upload/w_600,h_600,c_pad,b_white,q_auto,f_png/guildforge/settings/logo');
    }

    public function test_the_image_field_says_which_size_to_use(): void
    {
        $this->page()->assertSee('1200');
    }

    private function settings(): LinkPageSettingsInterface
    {
        return app(LinkPageSettingsInterface::class);
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
