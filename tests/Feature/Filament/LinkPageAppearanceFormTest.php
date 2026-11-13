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
use Modules\LinkPage\Domain\Enums\AppearanceTemplate;
use Modules\LinkPage\Domain\Enums\ButtonStyle;
use Modules\LinkPage\Domain\Enums\ThemeMode;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Modules\LinkPage\Tests\Support\InteractsWithMediaLibrary;
use Modules\LinkPage\Tests\Support\RecordingMediaFileReleaser;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The appearance section of the link page settings: templates fill the fields, colours must
 * pass WCAG AA on the server, uploaded images replace (and delete) the previous ones.
 */
final class LinkPageAppearanceFormTest extends ModuleTestCase
{
    use InteractsWithMediaLibrary;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        $this->registerOnAdminPanel([], [LinkPageSettingsPage::class]);
        $this->actingAsPanelAdmin();
    }

    public function test_the_form_opens_with_the_site_appearance(): void
    {
        $this->page()->assertFormSet([
            'appearance.template' => 'site',
            'appearance.theme' => 'device',
            'appearance.logo.source' => 'site',
            'appearance.logo.shape' => 'circle',
            'appearance.font' => 'site_body',
            'appearance.buttons.style' => 'outline',
            'appearance.buttons.shape' => 'rounded',
        ]);
    }

    public function test_picking_a_template_fills_the_fields(): void
    {
        $values = AppearanceTemplate::Arcane->values();
        $this->assertNotNull($values);

        $this->page()
            ->fillForm(['appearance.template' => 'arcane'])
            ->assertFormSet([
                'appearance.font' => $values['font'],
                'appearance.buttons.style' => $values['buttons']['style'],
                'appearance.buttons.shape' => $values['buttons']['shape'],
                'appearance.background.type' => $values['background']['type'],
                'appearance.background.angle' => $values['background']['angle'],
                'appearance.light.background' => $values['light']['background'],
                'appearance.light.button_text' => $values['light']['button_text'],
                'appearance.dark.text' => $values['dark']['text'],
            ]);
    }

    public function test_changing_a_colour_after_a_template_makes_it_custom(): void
    {
        $this->page()
            ->fillForm(['appearance.template' => 'minimal'])
            ->fillForm(['appearance.light.button' => '#1E3A8A'])
            ->assertFormSet(['appearance.template' => 'custom']);
    }

    public function test_it_saves_a_template_with_its_colours(): void
    {
        $this->page()
            ->fillForm(['appearance.template' => 'forest', 'appearance.theme' => 'dark', 'appearance.logo.shape' => 'rounded'])
            ->call('save')
            ->assertHasNoFormErrors();

        $appearance = app(LinkPageSettingsInterface::class)->appearance();
        $this->assertSame(AppearanceTemplate::Forest, $appearance->template);
        $this->assertSame(ThemeMode::Dark, $appearance->theme);
        $this->assertSame(ButtonStyle::Outline, $appearance->buttonStyle);
        $this->assertSame('#132017', $appearance->dark->background->value);
    }

    public function test_colours_below_aa_are_not_saved_and_the_field_says_why(): void
    {
        $this->page()
            ->fillForm(['appearance.template' => 'minimal'])
            ->fillForm(['appearance.light.text' => '#888888'])
            ->call('save')
            ->assertHasFormErrors(['appearance.light.text'])
            ->assertSee('3,2:1')
            ->assertSee('4,5:1');

        $this->assertSame(AppearanceTemplate::Site, app(LinkPageSettingsInterface::class)->appearance()->template);
    }

    public function test_an_outline_button_needs_its_colour_to_read_on_the_background(): void
    {
        $this->page()
            ->fillForm(['appearance.template' => 'minimal'])
            ->fillForm(['appearance.buttons.style' => 'outline', 'appearance.light.button' => '#808080', 'appearance.light.button_text' => '#000000'])
            ->call('save')
            ->assertHasFormErrors(['appearance.light.button'])
            ->assertHasNoFormErrors(['appearance.light.button_text', 'appearance.light.text']);
    }

    public function test_only_the_colour_sets_the_theme_shows_are_checked(): void
    {
        $this->page()
            ->fillForm(['appearance.template' => 'minimal'])
            ->fillForm(['appearance.theme' => 'light', 'appearance.dark.text' => '#111111'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(ThemeMode::Light, app(LinkPageSettingsInterface::class)->appearance()->theme);
    }

    public function test_colours_must_be_hex(): void
    {
        $this->page()
            ->fillForm(['appearance.template' => 'minimal'])
            ->fillForm(['appearance.light.text' => 'red;background:url(x)'])
            ->call('save')
            ->assertHasFormErrors(['appearance.light.text']);
    }

    public function test_a_colour_with_a_trailing_newline_is_refused(): void
    {
        $this->page()
            ->fillForm(['appearance.template' => 'minimal'])
            ->fillForm(['appearance.light.text' => "#111111\n"])
            ->call('save')
            ->assertHasFormErrors(['appearance.light.text' => 'regex']);
    }

    public function test_an_uploaded_logo_is_stored_on_the_images_disk_and_a_new_one_replaces_it(): void
    {
        $releaser = RecordingMediaFileReleaser::bind();
        $this->page()
            ->fillForm(['appearance.logo.source' => 'custom', 'appearance.logo.image' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->call('save')
            ->assertHasNoFormErrors();

        $first = app(LinkPageSettingsInterface::class)->appearance()->logoImage;
        $this->assertNotNull($first);
        $this->assertStringStartsWith('link-page/', $first);
        Storage::disk('images')->assertExists($first);

        // A single upload replaces its file in the browser; the test helper would add it beside
        $this->page()
            ->set('data.appearance.logo.image', [])
            ->fillForm(['appearance.logo.image' => UploadedFile::fake()->image('other.png', 200, 200)])
            ->call('save')
            ->assertHasNoFormErrors();

        $second = app(LinkPageSettingsInterface::class)->appearance()->logoImage;
        $this->assertNotSame($first, $second);
        Storage::disk('images')->assertExists($second);
        // Another record may have chosen the replaced file: the core checks before deleting it
        $this->assertSame([$first], $releaser->released);
        Storage::disk('images')->assertExists($first);
    }

    public function test_removing_the_background_image_releases_it_through_the_core(): void
    {
        $releaser = RecordingMediaFileReleaser::bind();
        Storage::disk('images')->put('link-page/2026/06/back.jpg', 'image');
        $values = AppearanceTemplate::Minimal->values();
        $this->assertNotNull($values);
        app(ModuleSettingsStoreInterface::class)->store('link-page', ['appearance' => [
            ...$values,
            'template' => 'minimal',
            'theme' => 'device',
            'logo' => ['source' => 'site', 'image' => null, 'shape' => 'circle'],
            'background' => ['type' => 'image', 'image' => 'link-page/2026/06/back.jpg', 'angle' => 180],
        ]]);
        $this->refreshModuleSettings();

        $this->page()
            ->fillForm(['appearance.background.type' => 'solid', 'appearance.background.image' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(app(LinkPageSettingsInterface::class)->appearance()->backgroundImage);
        $this->assertSame(['link-page/2026/06/back.jpg'], $releaser->released);
    }

    public function test_the_logo_and_background_fields_offer_the_library(): void
    {
        $this->storeAppearance(logo: 'link-page/2026/06/logo.png', background: 'link-page/2026/06/back.jpg');

        $this->assertFieldOffersLibrary($this->page(), 'appearance.logo.image');
        $this->assertFieldOffersLibrary($this->page(), 'appearance.background.image');
    }

    public function test_an_image_of_the_library_can_be_chosen_for_the_logo_and_the_background(): void
    {
        $releaser = RecordingMediaFileReleaser::bind();
        $this->indexLibraryFile('branding/logo', 'png');
        $this->indexLibraryFile('events/2026/11/poster', 'webp');
        $this->storeAppearance(logo: 'link-page/2026/06/logo.png', background: 'link-page/2026/06/back.jpg');

        $this->page()
            ->set('data.appearance.logo.image', ['chosen' => 'branding/logo.png'])
            ->set('data.appearance.background.image', ['chosen' => 'events/2026/11/poster.webp'])
            ->call('save')
            ->assertHasNoFormErrors();

        $appearance = app(LinkPageSettingsInterface::class)->appearance();
        $this->assertSame('branding/logo.png', $appearance->logoImage);
        $this->assertSame('events/2026/11/poster.webp', $appearance->backgroundImage);
        $this->assertSame(['link-page/2026/06/logo.png', 'link-page/2026/06/back.jpg'], $releaser->released);
    }

    public function test_an_image_moved_from_the_logo_to_the_background_is_not_released(): void
    {
        $releaser = RecordingMediaFileReleaser::bind();
        $this->storeAppearance(logo: 'link-page/2026/06/logo.png', background: 'link-page/2026/06/back.jpg');

        $this->page()
            ->set('data.appearance.logo.source', 'site')
            ->set('data.appearance.background.image', ['moved' => 'link-page/2026/06/logo.png'])
            ->call('save')
            ->assertHasNoFormErrors();

        $appearance = app(LinkPageSettingsInterface::class)->appearance();
        $this->assertNull($appearance->logoImage);
        $this->assertSame('link-page/2026/06/logo.png', $appearance->backgroundImage);
        $this->assertSame(['link-page/2026/06/back.jpg'], $releaser->released);
    }

    public function test_a_forged_path_is_refused_and_the_stored_images_kept(): void
    {
        $releaser = RecordingMediaFileReleaser::bind();
        Storage::disk('images')->put('events/2026/11/poster.jpg', 'poster');
        $this->storeAppearance(logo: 'link-page/2026/06/logo.png', background: 'link-page/2026/06/back.jpg');

        // A forged form state points the logo at a file that is not in the library
        $this->page()
            ->set('data.appearance.logo.image', ['forged' => 'events/2026/11/poster.jpg'])
            ->call('save')
            ->assertHasFormErrors(['appearance.logo.image']);

        $this->assertSame('link-page/2026/06/logo.png', app(LinkPageSettingsInterface::class)->appearance()->logoImage);
        $this->assertSame([], $releaser->released);
    }

    public function test_a_library_image_the_field_does_not_take_is_refused(): void
    {
        // SVG is not among the logo types, and the background takes up to 4 MB
        $this->indexLibraryFile('branding/logo', 'svg');
        $this->indexLibraryFile('events/2026/11/huge', 'jpg', bytes: 4097 * 1024);
        $this->storeAppearance(logo: 'link-page/2026/06/logo.png', background: 'link-page/2026/06/back.jpg');

        $this->page()
            ->set('data.appearance.logo.image', ['chosen' => 'branding/logo.svg'])
            ->set('data.appearance.background.image', ['chosen' => 'events/2026/11/huge.jpg'])
            ->call('save')
            ->assertHasFormErrors(['appearance.logo.image', 'appearance.background.image']);
    }

    public function test_a_library_image_whose_name_the_page_cannot_use_says_so_on_the_field(): void
    {
        $this->indexLibraryFile('events/2026/11/my poster', 'jpg');
        $this->storeAppearance(logo: 'link-page/2026/06/logo.png', background: 'link-page/2026/06/back.jpg');

        $this->page()
            ->set('data.appearance.background.image', ['chosen' => 'events/2026/11/my poster.jpg'])
            ->call('save')
            ->assertHasFormErrors(['appearance.background.image'])
            ->assertHasNoFormErrors(['appearance.template']);

        $this->assertSame('link-page/2026/06/back.jpg', app(LinkPageSettingsInterface::class)->appearance()->backgroundImage);
    }

    private function storeAppearance(string $logo, string $background): void
    {
        $values = AppearanceTemplate::Minimal->values();
        $this->assertNotNull($values);
        app(ModuleSettingsStoreInterface::class)->store('link-page', ['appearance' => [
            ...$values,
            'template' => 'minimal',
            'theme' => 'device',
            'logo' => ['source' => 'custom', 'image' => $logo, 'shape' => 'circle'],
            'background' => ['type' => 'image', 'image' => $background, 'angle' => 180],
        ]]);
        $this->refreshModuleSettings();
    }

    public function test_the_preview_shows_both_colour_sets_and_their_contrast(): void
    {
        $this->page()
            ->fillForm(['appearance.template' => 'minimal'])
            ->assertSeeHtml('data-preview="light"')
            ->assertSeeHtml('data-preview="dark"')
            ->assertSee(__('link-page::messages.appearance.preview.contrast.text'))
            ->fillForm(['appearance.theme' => 'dark'])
            ->assertDontSeeHtml('data-preview="light"')
            ->assertSeeHtml('data-preview="dark"');
    }

    public function test_each_preview_phone_shows_the_site_logo_of_its_theme(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_light', 'branding/light.png');
        app(SettingsServiceInterface::class)->set('site_logo_dark', 'branding/dark.png');

        $logos = $this->page()->instance()->appearancePreview()['logos'];

        $this->assertSame(['url' => Storage::disk('images')->url('branding/light.png'), 'isDark' => false], $logos['light']);
        $this->assertSame(['url' => Storage::disk('images')->url('branding/dark.png'), 'isDark' => true], $logos['dark']);
    }

    public function test_an_own_logo_not_saved_yet_shows_in_both_preview_phones(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_dark', 'branding/dark.png');

        $page = $this->page()->fillForm([
            'appearance.logo.source' => 'custom',
            'appearance.logo.image' => UploadedFile::fake()->image('logo.png', 256, 256),
        ]);
        $logos = $page->instance()->appearancePreview()['logos'];

        $this->assertNotNull($logos['light']['url']);
        $this->assertStringNotContainsString('branding/dark.png', $logos['light']['url']);
        $this->assertSame($logos['light'], $logos['dark']);
        $this->assertFalse($logos['dark']['isDark']);
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
