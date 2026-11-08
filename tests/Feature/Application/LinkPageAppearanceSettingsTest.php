<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Application;

use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Domain\Enums\AppearanceTemplate;
use Modules\LinkPage\Domain\Enums\ButtonStyle;
use Modules\LinkPage\Domain\Enums\PageFont;
use Modules\LinkPage\Domain\Enums\ThemeMode;
use Modules\LinkPage\Domain\ValueObjects\Appearance;
use Tests\Support\Modules\ModuleTestCase;

final class LinkPageAppearanceSettingsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    public function test_the_release_defaults_are_the_domain_defaults(): void
    {
        $this->assertSame(Appearance::defaults()->toArray(), config('modules.settings.link-page.appearance'));
        $this->assertEquals(Appearance::defaults(), $this->settings()->appearance());
    }

    public function test_saved_values_win_and_missing_keys_keep_their_defaults(): void
    {
        config()->set('modules.settings.link-page.appearance', [
            'template' => 'arcane',
            'theme' => 'dark',
            'font' => 'playfair',
            'buttons' => ['style' => 'shadow'],
        ]);

        $appearance = $this->settings()->appearance();

        $this->assertSame(AppearanceTemplate::Arcane, $appearance->template);
        $this->assertSame(ThemeMode::Dark, $appearance->theme);
        $this->assertSame(PageFont::Playfair, $appearance->font);
        $this->assertSame(ButtonStyle::Shadow, $appearance->buttonStyle);
        $this->assertSame(Appearance::defaults()->buttonShape, $appearance->buttonShape);
    }

    public function test_a_malformed_appearance_falls_back_to_the_defaults(): void
    {
        config()->set('modules.settings.link-page.appearance', ['template' => 'custom', 'font' => 'Comic Sans']);
        $this->assertEquals(Appearance::defaults(), $this->settings()->appearance());

        config()->set('modules.settings.link-page.appearance', 'pink');
        $this->assertEquals(Appearance::defaults(), $this->settings()->appearance());
    }

    public function test_stored_colours_below_aa_fall_back_to_the_site_colours(): void
    {
        // Written outside the settings page (tinker, an old release): the page must stay readable
        config()->set('modules.settings.link-page.appearance', [
            'template' => 'custom',
            'theme' => 'light',
            'font' => 'inter',
            'light' => ['background' => '#FFFFFF', 'background_to' => '#FFFFFF', 'text' => '#EEEEEE', 'button' => '#111111', 'button_text' => '#FFFFFF', 'overlay' => 60],
        ]);

        $appearance = $this->settings()->appearance();

        $this->assertSame(AppearanceTemplate::Site, $appearance->template);
        $this->assertSame(ThemeMode::Light, $appearance->theme);
        $this->assertSame(PageFont::Inter, $appearance->font);
    }

    private function settings(): LinkPageSettingsInterface
    {
        return app(LinkPageSettingsInterface::class);
    }
}
