<?php

declare(strict_types=1);

namespace Modules\LinkPage\Http\Presenters;

use Illuminate\Support\Facades\Storage;
use Modules\LinkPage\Domain\Enums\BackgroundType;
use Modules\LinkPage\Domain\Enums\LogoSource;
use Modules\LinkPage\Domain\ValueObjects\Appearance;
use Modules\LinkPage\Domain\ValueObjects\ColorScheme;

/**
 * The appearance as the page (Show.vue) and the panel preview read it: colours as #RRGGBB,
 * the font as a fixed CSS stack, images as URLs of the images disk.
 */
final readonly class AppearancePresenter
{
    /**
     * @return array{
     *     theme: string,
     *     colors: array{light: array<string, string|float>, dark: array<string, string|float>}|null,
     *     background: array{type: string, imageUrl: string|null, angle: int},
     *     buttons: array{style: string, shape: string},
     *     font: string,
     *     logo: array{shape: string, custom: bool},
     * }
     */
    public function props(Appearance $appearance): array
    {
        $siteColors = $appearance->template->usesSiteColors();
        $background = $siteColors ? BackgroundType::Solid : $appearance->effectiveBackgroundType();

        return [
            'theme' => $appearance->theme->value,
            'colors' => $siteColors ? null : [
                'light' => $this->colors($appearance->light),
                'dark' => $this->colors($appearance->dark),
            ],
            'background' => [
                'type' => $background->value,
                'imageUrl' => $background === BackgroundType::Image ? $this->url($appearance->backgroundImage) : null,
                'angle' => $appearance->gradientAngle,
            ],
            'buttons' => ['style' => $appearance->buttonStyle->value, 'shape' => $appearance->buttonShape->value],
            'font' => $appearance->font->stack(),
            'logo' => [
                'shape' => $appearance->logoShape->value,
                'custom' => $appearance->effectiveLogoSource() === LogoSource::Custom,
            ],
        ];
    }

    /**
     * The picture at the top: the module's own image, the site logo, or none for the initial.
     *
     * @param  string|null  $siteLogoUrl  the site logo, as SiteBrandingServiceInterface gives it
     * @return array{url: string|null, isDark: bool}
     */
    public function logo(Appearance $appearance, ?string $siteLogoUrl, bool $siteLogoIsDark): array
    {
        return match ($appearance->effectiveLogoSource()) {
            LogoSource::Custom => ['url' => $this->url($appearance->logoImage), 'isDark' => false],
            LogoSource::Initial => ['url' => null, 'isDark' => false],
            LogoSource::Site => ['url' => $siteLogoUrl, 'isDark' => $siteLogoUrl !== null && $siteLogoIsDark],
        };
    }

    /**
     * @return array{background: string, backgroundTo: string, text: string, button: string, buttonText: string, buttonStrong: string, muted: string, overlay: float}
     */
    private function colors(ColorScheme $scheme): array
    {
        return [
            'background' => $scheme->background->value,
            'backgroundTo' => $scheme->backgroundTo->value,
            'text' => $scheme->text->value,
            'button' => $scheme->button->value,
            'buttonText' => $scheme->buttonText->value,
            'buttonStrong' => $scheme->strongButton()->value,
            'muted' => $scheme->muted()->value,
            'overlay' => round($scheme->overlay / 100, 2),
        ];
    }

    private function url(?string $path): ?string
    {
        return $path !== null ? Storage::disk('images')->url($path) : null;
    }
}
