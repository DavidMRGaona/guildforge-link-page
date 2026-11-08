<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use App\Application\Services\SettingsServiceInterface;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\LinkPage\Domain\Enums\AppearanceTemplate;
use Tests\Support\Modules\ModuleTestCase;

final class LinkPageAppearanceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('images');
    }

    public function test_by_default_the_page_keeps_the_site_colours_and_logo(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_light', 'branding/logo.png');

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.logo.light', ['url' => Storage::disk('images')->url('branding/logo.png'), 'isDark' => false])
                // With a single site logo, the dark theme shows it too
                ->where('site.logo.dark', ['url' => Storage::disk('images')->url('branding/logo.png'), 'isDark' => false])
                ->where('appearance.theme', 'device')
                ->where('appearance.colors', null)
                ->where('appearance.background.type', 'solid')
                ->where('appearance.background.imageUrl', null)
                ->where('appearance.buttons', ['style' => 'outline', 'shape' => 'rounded'])
                ->where('appearance.font', 'var(--font-body, system-ui, sans-serif)')
                ->where('appearance.logo', ['shape' => 'circle', 'custom' => false])
            );
    }

    public function test_a_template_sends_both_colour_sets_and_the_selected_calendar_button(): void
    {
        $values = AppearanceTemplate::Arcane->values();
        $this->assertNotNull($values);
        config()->set('modules.settings.link-page.appearance', [...$values, 'template' => 'arcane', 'theme' => 'dark']);

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('appearance.theme', 'dark')
                ->where('appearance.colors.light.background', '#EDE7FB')
                ->where('appearance.colors.dark.text', '#F1EAFF')
                ->where('appearance.colors.dark.buttonText', '#1B1030')
                ->where('appearance.colors.dark.overlay', 0.6)
                ->has('appearance.colors.dark.buttonStrong')
                ->where('appearance.background.type', 'gradient')
                ->where('appearance.background.angle', 135)
                ->where('appearance.buttons', ['style' => 'filled', 'shape' => 'pill'])
            );
    }

    public function test_own_images_come_from_the_images_disk(): void
    {
        config()->set('modules.settings.link-page.appearance', [
            'template' => 'minimal',
            'logo' => ['source' => 'custom', 'image' => 'link-page/2026/06/logo.png', 'shape' => 'rounded'],
            'background' => ['type' => 'image', 'image' => 'link-page/2026/06/back.jpg', 'angle' => 180],
        ]);

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.logo.light', ['url' => Storage::disk('images')->url('link-page/2026/06/logo.png'), 'isDark' => false])
                ->where('site.logo.dark', ['url' => Storage::disk('images')->url('link-page/2026/06/logo.png'), 'isDark' => false])
                ->where('appearance.logo', ['shape' => 'rounded', 'custom' => true])
                ->where('appearance.background.type', 'image')
                ->where('appearance.background.imageUrl', Storage::disk('images')->url('link-page/2026/06/back.jpg'))
            );
    }

    public function test_the_initial_hides_the_site_logo(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_light', 'branding/logo.png');
        config()->set('modules.settings.link-page.appearance.logo', ['source' => 'initial', 'image' => null, 'shape' => 'circle']);

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.logo.light', ['url' => null, 'isDark' => false])
                ->where('site.logo.dark', ['url' => null, 'isDark' => false])
            );
    }

    public function test_each_theme_gets_the_site_logo_made_for_it(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_light', 'branding/light.png');
        app(SettingsServiceInterface::class)->set('site_logo_dark', 'branding/dark.png');

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.logo.light', ['url' => Storage::disk('images')->url('branding/light.png'), 'isDark' => false])
                ->where('site.logo.dark', ['url' => Storage::disk('images')->url('branding/dark.png'), 'isDark' => true])
            );
    }

    public function test_a_site_with_only_the_dark_logo_shows_it_in_both_themes(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_dark', 'branding/dark.png');

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.logo.light', ['url' => Storage::disk('images')->url('branding/dark.png'), 'isDark' => true])
                ->where('site.logo.dark', ['url' => Storage::disk('images')->url('branding/dark.png'), 'isDark' => true])
            );
    }
}
