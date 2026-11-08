<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Modules\LinkPage\Application\Contracts\SiteSocialProfilesInterface;
use Modules\LinkPage\Domain\Enums\SocialNetwork;
use Modules\LinkPage\Domain\Enums\SocialPosition;
use Modules\LinkPage\Domain\ValueObjects\LinkUrl;

/**
 * The row of social icons: where it goes and which of the site's networks it shows. The
 * addresses are never typed here: they are the site's own (Sobre nosotros → Redes sociales and
 * Contacto), so every module reads the same ones. Form state lives under "social" (position,
 * shown).
 */
final class SocialSection
{
    public static function make(): Section
    {
        return Section::make(__('link-page::messages.social.section'))
            ->description(__('link-page::messages.social.section_help', [
                'group' => __('filament.navigation.pages'),
                'page' => __('filament.pages.about.title'),
                'social' => __('filament.pages.about.tabs.social'),
                'contact' => __('filament.pages.about.tabs.contact'),
            ]))
            ->schema([
                Select::make('social.position')
                    ->label(__('link-page::messages.social.position'))
                    ->options(SocialPosition::options())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->required(),
                CheckboxList::make('social.shown')
                    ->label(__('link-page::messages.social.shown'))
                    ->helperText(__('link-page::messages.social.shown_help'))
                    ->options(SocialNetwork::options())
                    ->descriptions(fn (): array => self::descriptions())
                    // A network the site has no address for cannot be ticked: it would show nothing
                    ->disableOptionWhen(fn (string $value): bool => ! self::hasAddress($value))
                    ->columns(['sm' => 2])
                    ->bulkToggleable(),
            ]);
    }

    /**
     * What the form opens with: the ticked networks the site still has an address for.
     *
     * @param  list<SocialNetwork>  $shown
     * @return list<string>
     */
    public static function formState(array $shown): array
    {
        $available = app(SiteSocialProfilesInterface::class)->all();

        return array_values(array_map(
            static fn (SocialNetwork $network): string => $network->value,
            array_filter($shown, static fn (SocialNetwork $network): bool => isset($available[$network->value])),
        ));
    }

    /**
     * What the form state becomes in the settings: the known networks ticked, in catalogue
     * order, and the position. An empty list is kept as such (nothing shown), never dropped.
     *
     * @param  array<array-key, mixed>  $state  the "social" part of the form
     * @return array{position: string, shown: list<string>}
     */
    public static function toSettings(array $state): array
    {
        $position = is_string($state['position'] ?? null) ? SocialPosition::tryFrom($state['position']) : null;
        $ticked = is_array($state['shown'] ?? null) ? $state['shown'] : [];

        $shown = array_values(array_map(
            static fn (SocialNetwork $network): string => $network->value,
            array_filter(SocialNetwork::cases(), static fn (SocialNetwork $network): bool => in_array($network->value, $ticked, true)),
        ));

        return [
            'position' => ($position ?? SocialPosition::Bottom)->value,
            'shown' => $shown,
        ];
    }

    /**
     * Under each network, the address the site has for it, or that it has none.
     *
     * @return array<string, string>
     */
    private static function descriptions(): array
    {
        $available = app(SiteSocialProfilesInterface::class)->all();
        $descriptions = [];

        foreach (SocialNetwork::cases() as $network) {
            $url = $available[$network->value] ?? null;
            $descriptions[$network->value] = $url instanceof LinkUrl
                ? self::readable($url)
                : __('link-page::messages.social.no_address');
        }

        return $descriptions;
    }

    private static function hasAddress(string $network): bool
    {
        return array_key_exists($network, app(SiteSocialProfilesInterface::class)->all());
    }

    /** The address without its scheme: www.instagram.com/example, info@example.org, +34600123456. */
    private static function readable(LinkUrl $url): string
    {
        return ltrim(substr($url->value, strlen($url->scheme()) + 1), '/');
    }
}
