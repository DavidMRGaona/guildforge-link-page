<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * Where the page colours come from. Site follows the site palette live (the page as the core
 * paints it); the named templates only fill the fields, which can then be changed (the
 * template becomes Custom). Every named template passes WCAG AA in both colour sets with any
 * button style (AppearanceTemplateTest).
 */
enum AppearanceTemplate: string
{
    use HasTranslatedOptions;

    case Site = 'site';
    case Parchment = 'parchment';
    case Tavern = 'tavern';
    case Arcane = 'arcane';
    case Forest = 'forest';
    case Minimal = 'minimal';
    case Custom = 'custom';

    public function usesSiteColors(): bool
    {
        return $this === self::Site;
    }

    /**
     * @return list<self> the templates that fill the fields
     */
    public static function named(): array
    {
        return [self::Parchment, self::Tavern, self::Arcane, self::Forest, self::Minimal];
    }

    /**
     * What a named template fills, in the shape of the stored settings; null for Site and Custom.
     *
     * @return array{
     *     font: string,
     *     buttons: array{style: string, shape: string},
     *     background: array{type: string, angle: int},
     *     light: array{background: string, background_to: string, text: string, button: string, button_text: string, overlay: int},
     *     dark: array{background: string, background_to: string, text: string, button: string, button_text: string, overlay: int},
     * }|null
     */
    public function values(): ?array
    {
        return match ($this) {
            self::Site, self::Custom => null,
            self::Parchment => [
                'font' => PageFont::Playfair->value,
                'buttons' => ['style' => ButtonStyle::Filled->value, 'shape' => ButtonShape::Rounded->value],
                'background' => ['type' => BackgroundType::Solid->value, 'angle' => 180],
                'light' => self::scheme('#F4E9D0', '#E8D5AE', '#3B2A1A', '#7A2E1D', '#FFF8EC'),
                'dark' => self::scheme('#2A2118', '#3A2D20', '#F4E9D0', '#E3B66B', '#2A2118'),
            ],
            self::Tavern => [
                'font' => PageFont::SiteHeading->value,
                'buttons' => ['style' => ButtonStyle::Shadow->value, 'shape' => ButtonShape::Rounded->value],
                'background' => ['type' => BackgroundType::Gradient->value, 'angle' => 180],
                'light' => self::scheme('#F3E7D7', '#E2CDB0', '#2E1F14', '#6B3E1F', '#FFFFFF'),
                'dark' => self::scheme('#1E1510', '#3A2618', '#F3E6D3', '#D08A3C', '#1E1510'),
            ],
            self::Arcane => [
                'font' => PageFont::SystemSans->value,
                'buttons' => ['style' => ButtonStyle::Filled->value, 'shape' => ButtonShape::Pill->value],
                'background' => ['type' => BackgroundType::Gradient->value, 'angle' => 135],
                'light' => self::scheme('#EDE7FB', '#D9CCF5', '#2A1846', '#5B2DB3', '#FFFFFF'),
                'dark' => self::scheme('#1B1030', '#3A1F66', '#F1EAFF', '#C9B2FF', '#1B1030'),
            ],
            self::Forest => [
                'font' => PageFont::SystemSerif->value,
                'buttons' => ['style' => ButtonStyle::Outline->value, 'shape' => ButtonShape::Rounded->value],
                'background' => ['type' => BackgroundType::Solid->value, 'angle' => 180],
                'light' => self::scheme('#E8F1E4', '#D3E4CC', '#1F3320', '#2F6B35', '#FFFFFF'),
                'dark' => self::scheme('#132017', '#1F3324', '#E3F0E0', '#8CCB8F', '#132017'),
            ],
            self::Minimal => [
                'font' => PageFont::SystemSans->value,
                'buttons' => ['style' => ButtonStyle::Filled->value, 'shape' => ButtonShape::Square->value],
                'background' => ['type' => BackgroundType::Solid->value, 'angle' => 180],
                'light' => self::scheme('#FFFFFF', '#F2F2F2', '#111111', '#111111', '#FFFFFF'),
                'dark' => self::scheme('#0A0A0A', '#1C1C1C', '#F5F5F5', '#F5F5F5', '#0A0A0A'),
            ],
        };
    }

    protected static function translationGroup(): string
    {
        return 'templates';
    }

    /**
     * The overlay starts at 60 %, enough for these colours on any image.
     *
     * @return array{background: string, background_to: string, text: string, button: string, button_text: string, overlay: int}
     */
    private static function scheme(string $background, string $backgroundTo, string $text, string $button, string $buttonText): array
    {
        return [
            'background' => $background,
            'background_to' => $backgroundTo,
            'text' => $text,
            'button' => $button,
            'button_text' => $buttonText,
            'overlay' => 60,
        ];
    }
}
