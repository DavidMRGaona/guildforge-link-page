<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Support;

use App\Application\Services\ThemeSettingsServiceInterface;
use App\Domain\ValueObjects\ColorPalette;
use App\Domain\ValueObjects\HexColor;
use Modules\LinkPage\Domain\Enums\BackgroundType;
use Modules\LinkPage\Domain\Enums\ButtonShape;
use Modules\LinkPage\Domain\Enums\ButtonStyle;
use Modules\LinkPage\Domain\Enums\LogoShape;
use Modules\LinkPage\Domain\ValueObjects\Appearance;
use Modules\LinkPage\Domain\ValueObjects\ContrastCheck;

/**
 * The phone mock-ups of the settings page, one per colour set the theme shows. The panel does
 * not compile the module's classes, so everything is inline styles built here from validated
 * values (colours, fixed font stacks, URLs of the images disk).
 */
final readonly class AppearancePreview
{
    public function __construct(private ThemeSettingsServiceInterface $theme) {}

    /**
     * @param  string|null  $backgroundUrl  the background image, when the background is one
     * @return list<array{
     *     set: string,
     *     label: string,
     *     page: string,
     *     overlay: string|null,
     *     text: string,
     *     button: string,
     *     meta: string,
     *     badge: string,
     *     logo: string,
     *     initial: string,
     *     checks: list<array{label: string, ratio: string, minimum: string, passes: bool}>,
     * }>
     */
    public function phones(Appearance $appearance, ?string $backgroundUrl): array
    {
        $phones = [];

        foreach ($appearance->theme->schemesInUse() as $set) {
            $phones[] = $appearance->template->usesSiteColors()
                ? $this->sitePhone($appearance, $set)
                : $this->customPhone($appearance, $set, $backgroundUrl);
        }

        return $phones;
    }

    /**
     * @return array{set: string, label: string, page: string, overlay: string|null, text: string, button: string, meta: string, badge: string, logo: string, initial: string, checks: list<array{label: string, ratio: string, minimum: string, passes: bool}>}
     */
    private function customPhone(Appearance $appearance, string $set, ?string $backgroundUrl): array
    {
        $scheme = $set === 'light' ? $appearance->light : $appearance->dark;
        $type = $appearance->effectiveBackgroundType();
        $bg = $scheme->background->value;
        $outline = $appearance->buttonStyle === ButtonStyle::Outline;

        $page = match ($type) {
            BackgroundType::Solid => "background-color: {$bg};",
            BackgroundType::Gradient => "background-color: {$bg}; background-image: linear-gradient({$appearance->gradientAngle}deg, {$bg}, {$scheme->backgroundTo->value});",
            BackgroundType::Image => "background-color: {$bg};".($backgroundUrl !== null ? ' background-image: url("'.$this->cssUrl($backgroundUrl).'"); background-size: cover; background-position: center;' : ''),
        };

        $button = $outline
            ? "background-color: transparent; color: {$scheme->button->value}; border: 2px solid {$scheme->button->value};"
            : "background-color: {$scheme->button->value}; color: {$scheme->buttonText->value}; border: 2px solid transparent;";

        if ($appearance->buttonStyle === ButtonStyle::Shadow) {
            $button .= " box-shadow: 4px 4px 0 0 {$scheme->text->value};";
        }

        return [
            'set' => $set,
            'label' => __("link-page::messages.appearance.{$set}"),
            'page' => $page.' color: '.$scheme->text->value.'; font-family: '.$appearance->font->stack().';',
            'overlay' => $type === BackgroundType::Image ? "background-color: {$bg}; opacity: ".($scheme->overlay / 100).';' : null,
            'text' => "color: {$scheme->text->value};",
            'button' => $button.' border-radius: '.$this->radius($appearance->buttonShape).';',
            'meta' => 'color: '.($outline ? $scheme->button->value : $scheme->buttonText->value).';',
            'badge' => $outline
                ? "background-color: {$scheme->button->value}; color: {$scheme->buttonText->value};"
                : "background-color: {$scheme->buttonText->value}; color: {$scheme->button->value};",
            'logo' => 'border-radius: '.$this->logoRadius($appearance->logoShape).';',
            'initial' => "background-color: {$scheme->button->value}; color: {$scheme->buttonText->value}; border-radius: ".$this->logoRadius($appearance->logoShape).';',
            'checks' => array_map(
                fn (ContrastCheck $check): array => [
                    'label' => __("link-page::messages.appearance.preview.contrast.{$check->pair->value}"),
                    'ratio' => AppearanceFormState::ratio($check->ratio),
                    'minimum' => AppearanceFormState::ratio($check->minimum),
                    'passes' => $check->passes(),
                ],
                $scheme->contrastChecks($type, $appearance->buttonStyle),
            ),
        ];
    }

    /**
     * The site palette, approximated with the shades the core uses for its tokens.
     *
     * @return array{set: string, label: string, page: string, overlay: string|null, text: string, button: string, meta: string, badge: string, logo: string, initial: string, checks: list<array{label: string, ratio: string, minimum: string, passes: bool}>}
     */
    private function sitePhone(Appearance $appearance, string $set): array
    {
        $neutral = $this->theme->getNeutralPalette();
        $primaryPalette = $this->theme->getPrimaryPalette();
        $white = new HexColor('#FFFFFF');
        $light = $set === 'light';

        $page = $this->shade($neutral, $light ? 50 : 950);
        $surface = $light ? $white : $this->shade($neutral, 900);
        $text = $this->shade($neutral, $light ? 900 : 50);
        $secondary = $this->shade($neutral, $light ? 600 : 300);
        $border = $this->shade($neutral, $light ? 200 : 700);
        $primary = $this->shade($primaryPalette, $light ? 600 : 500)->readableOn([$white], lighten: false);
        $primaryLight = $this->shade($primaryPalette, $light ? 100 : 950);
        $radius = $this->radius($appearance->buttonShape);

        $filled = $appearance->buttonStyle !== ButtonStyle::Outline;
        $button = $filled
            ? "background-color: {$primary->value}; color: #FFFFFF; border: 1px solid transparent;"
            : "background-color: {$surface->value}; color: {$text->value}; border: 1px solid {$border->value};";

        if ($appearance->buttonStyle === ButtonStyle::Shadow) {
            $button .= " box-shadow: 4px 4px 0 0 {$text->value};";
        }

        return [
            'set' => $set,
            'label' => __("link-page::messages.appearance.{$set}"),
            'page' => "background-color: {$page->value}; color: {$text->value}; font-family: ".$appearance->font->stack().';',
            'overlay' => null,
            'text' => "color: {$text->value};",
            'button' => $button." border-radius: {$radius};",
            'meta' => 'color: '.($filled ? '#FFFFFF' : $secondary->value).';',
            'badge' => $filled
                ? "background-color: #FFFFFF; color: {$primary->value};"
                : "background-color: {$primaryLight->value}; color: {$text->value};",
            'logo' => 'border-radius: '.$this->logoRadius($appearance->logoShape).';',
            'initial' => "background-color: {$primary->value}; color: #FFFFFF; border-radius: ".$this->logoRadius($appearance->logoShape).';',
            'checks' => [],
        ];
    }

    private function radius(ButtonShape $shape): string
    {
        return match ($shape) {
            ButtonShape::Square => '0',
            ButtonShape::Rounded => '0.75rem',
            ButtonShape::Pill => '9999px',
        };
    }

    private function logoRadius(LogoShape $shape): string
    {
        return $shape === LogoShape::Circle ? '9999px' : '1rem';
    }

    private function shade(ColorPalette $palette, int $shade): HexColor
    {
        return $palette->shade($shade) ?? $palette->baseColor();
    }

    /** URLs of the images disk carry no quotes or backslashes; strip them anyway. */
    private function cssUrl(string $url): string
    {
        return str_replace(['"', '\\', "\n", "\r"], '', $url);
    }
}
