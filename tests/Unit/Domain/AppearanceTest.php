<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use InvalidArgumentException;
use Modules\LinkPage\Domain\Enums\AppearanceTemplate;
use Modules\LinkPage\Domain\Enums\BackgroundType;
use Modules\LinkPage\Domain\Enums\ButtonShape;
use Modules\LinkPage\Domain\Enums\ButtonStyle;
use Modules\LinkPage\Domain\Enums\ContrastPair;
use Modules\LinkPage\Domain\Enums\LogoShape;
use Modules\LinkPage\Domain\Enums\LogoSource;
use Modules\LinkPage\Domain\Enums\PageFont;
use Modules\LinkPage\Domain\Enums\ThemeMode;
use Modules\LinkPage\Domain\ValueObjects\Appearance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppearanceTest extends TestCase
{
    public function test_the_defaults_keep_the_page_as_the_site_paints_it(): void
    {
        $appearance = Appearance::defaults();

        $this->assertSame(AppearanceTemplate::Site, $appearance->template);
        $this->assertSame(ThemeMode::Device, $appearance->theme);
        $this->assertSame(LogoSource::Site, $appearance->logoSource);
        $this->assertSame(LogoShape::Circle, $appearance->logoShape);
        $this->assertSame(PageFont::SiteBody, $appearance->font);
        $this->assertSame(ButtonStyle::Outline, $appearance->buttonStyle);
        $this->assertSame(ButtonShape::Rounded, $appearance->buttonShape);
        $this->assertSame(BackgroundType::Solid, $appearance->backgroundType);
        $this->assertSame([], $appearance->contrastFailures());
    }

    public function test_it_round_trips_through_the_stored_shape(): void
    {
        $stored = $this->stored([
            'template' => 'custom',
            'theme' => 'dark',
            'logo' => ['source' => 'custom', 'image' => 'link-page/2026/06/logo.png', 'shape' => 'rounded'],
            'background' => ['type' => 'image', 'image' => 'link-page/2026/06/back.jpg', 'angle' => 90],
        ]);

        $this->assertSame($stored, Appearance::fromArray($stored)->toArray());
    }

    public function test_site_colours_are_never_checked(): void
    {
        $appearance = Appearance::fromArray($this->stored(['template' => 'site', 'light' => $this->unreadable()]));

        $this->assertSame([], $appearance->contrastFailures());
    }

    public function test_only_the_colour_sets_the_theme_shows_are_checked(): void
    {
        $lightOnly = Appearance::fromArray($this->stored(['template' => 'custom', 'theme' => 'light', 'dark' => $this->unreadable()]));
        $this->assertSame([], $lightOnly->contrastFailures());

        $device = Appearance::fromArray($this->stored(['template' => 'custom', 'theme' => 'device', 'dark' => $this->unreadable()]));
        $failures = $device->contrastFailures();
        $this->assertSame(['dark'], array_keys($failures));
        $this->assertSame(ContrastPair::Text, $failures['dark'][0]->pair);
    }

    public function test_an_image_background_without_an_image_is_a_solid_one(): void
    {
        $appearance = Appearance::fromArray($this->stored(['background' => ['type' => 'image', 'image' => null, 'angle' => 180]]));

        $this->assertSame(BackgroundType::Solid, $appearance->effectiveBackgroundType());
    }

    public function test_a_custom_logo_without_an_image_shows_the_site_logo(): void
    {
        $appearance = Appearance::fromArray($this->stored(['logo' => ['source' => 'custom', 'image' => null, 'shape' => 'circle']]));

        $this->assertSame(LogoSource::Site, $appearance->effectiveLogoSource());
    }

    public function test_with_site_colours_keeps_everything_but_the_colours(): void
    {
        $appearance = Appearance::fromArray($this->stored(['template' => 'arcane', 'theme' => 'dark']))->withSiteColors();

        $this->assertSame(AppearanceTemplate::Site, $appearance->template);
        $this->assertSame(ThemeMode::Dark, $appearance->theme);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformed(): iterable
    {
        yield 'unknown template' => [['template' => 'neon']];
        yield 'unknown theme' => [['theme' => 'sepia']];
        yield 'unknown font' => [['font' => 'Comic Sans']];
        yield 'angle out of range' => [['background' => ['type' => 'gradient', 'image' => null, 'angle' => 400]]];
        yield 'path outside the module folder' => [['logo' => ['source' => 'custom', 'image' => 'branding/logo.png', 'shape' => 'circle']]];
        yield 'path climbing up' => [['background' => ['type' => 'image', 'image' => 'link-page/../secret.png', 'angle' => 180]]];
        yield 'path with a quote' => [['background' => ['type' => 'image', 'image' => 'link-page/a").png', 'angle' => 180]]];
        yield 'colour set not a list' => [['light' => 'white']];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('malformed')]
    public function test_malformed_values_are_refused(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);

        Appearance::fromArray($this->stored($override));
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function stored(array $override = []): array
    {
        return array_replace([
            'template' => 'custom',
            'theme' => 'device',
            'logo' => ['source' => 'site', 'image' => null, 'shape' => 'circle'],
            'font' => 'inter',
            'buttons' => ['style' => 'filled', 'shape' => 'pill'],
            'background' => ['type' => 'solid', 'image' => null, 'angle' => 180],
            'light' => ['background' => '#FFFFFF', 'background_to' => '#F2F2F2', 'text' => '#111111', 'button' => '#111111', 'button_text' => '#FFFFFF', 'overlay' => 60],
            'dark' => ['background' => '#0A0A0A', 'background_to' => '#1C1C1C', 'text' => '#F5F5F5', 'button' => '#F5F5F5', 'button_text' => '#0A0A0A', 'overlay' => 60],
        ], $override);
    }

    /**
     * @return array<string, mixed>
     */
    private function unreadable(): array
    {
        return ['background' => '#FFFFFF', 'background_to' => '#FFFFFF', 'text' => '#EEEEEE', 'button' => '#111111', 'button_text' => '#FFFFFF', 'overlay' => 60];
    }
}
