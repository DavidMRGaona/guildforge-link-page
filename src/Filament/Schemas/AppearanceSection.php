<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Schemas;

use App\Filament\Forms\Components\MediaLibraryPicker;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\LinkPage\Domain\Enums\AppearanceTemplate;
use Modules\LinkPage\Domain\Enums\BackgroundType;
use Modules\LinkPage\Domain\Enums\ButtonShape;
use Modules\LinkPage\Domain\Enums\ButtonStyle;
use Modules\LinkPage\Domain\Enums\LogoShape;
use Modules\LinkPage\Domain\Enums\LogoSource;
use Modules\LinkPage\Domain\Enums\PageFont;
use Modules\LinkPage\Domain\Enums\ThemeMode;
use Modules\LinkPage\Domain\ValueObjects\Appearance;
use Modules\LinkPage\Domain\ValueObjects\ColorScheme;
use Modules\LinkPage\Filament\Support\StoredSettingsImages;

/**
 * The appearance section of the link page settings, with the live preview beside it.
 * Its state lives under "appearance", in the stored shape (Appearance::toArray()).
 */
final class AppearanceSection
{
    private const string KEY = 'appearance';

    /** Colour fields of a set, in the order they are shown. */
    private const array COLOR_FIELDS = ['background', 'background_to', 'text', 'button', 'button_text'];

    /** The panel's admin uploads, as in the core: image files under link-page/<year>/<month>. */
    private const array IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    public static function make(): Section
    {
        /** @var view-string $preview */
        $preview = 'link-page::filament.appearance-preview';

        return Section::make(__('link-page::messages.appearance.section'))
            ->description(__('link-page::messages.appearance.section_help'))
            ->schema([
                Grid::make(['default' => 1, 'xl' => 5])->schema([
                    Group::make(self::fields())->columnSpan(['default' => 1, 'xl' => 3]),
                    View::make($preview)->columnSpan(['default' => 1, 'xl' => 2]),
                ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function fields(): array
    {
        return [
            Select::make(self::KEY.'.template')
                ->label(__('link-page::messages.appearance.template'))
                ->helperText(__('link-page::messages.appearance.template_help'))
                ->options(AppearanceTemplate::options())
                ->native(false)
                ->selectablePlaceholder(false)
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $values = $state !== null ? AppearanceTemplate::tryFrom($state)?->values() : null;

                    if ($values === null) {
                        return;
                    }

                    $set(self::KEY.'.font', $values['font']);
                    $set(self::KEY.'.buttons.style', $values['buttons']['style']);
                    $set(self::KEY.'.buttons.shape', $values['buttons']['shape']);
                    $set(self::KEY.'.background.type', $values['background']['type']);
                    $set(self::KEY.'.background.angle', $values['background']['angle']);
                    foreach (['light', 'dark'] as $scheme) {
                        foreach ($values[$scheme] as $field => $value) {
                            $set(self::KEY.".{$scheme}.{$field}", $value);
                        }
                    }
                }),
            Select::make(self::KEY.'.theme')
                ->label(__('link-page::messages.appearance.theme'))
                ->helperText(__('link-page::messages.appearance.theme_help'))
                ->options(ThemeMode::options())
                ->native(false)
                ->selectablePlaceholder(false)
                ->required()
                ->live(),
            Fieldset::make(__('link-page::messages.appearance.logo'))
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Select::make(self::KEY.'.logo.source')
                        ->label(__('link-page::messages.appearance.logo_source'))
                        ->options(LogoSource::options())
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    Select::make(self::KEY.'.logo.shape')
                        ->label(__('link-page::messages.appearance.logo_shape'))
                        ->options(LogoShape::options())
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    self::image(self::KEY.'.logo.image', 'logo_image', maxKilobytes: 1024)
                        ->avatar()
                        ->visible(fn (Get $get): bool => $get(self::KEY.'.logo.source') === LogoSource::Custom->value)
                        ->columnSpanFull(),
                ]),
            Grid::make(['default' => 1, 'sm' => 3])->schema([
                Select::make(self::KEY.'.font')
                    ->label(__('link-page::messages.appearance.font'))
                    ->helperText(__('link-page::messages.appearance.font_help'))
                    ->options(PageFont::options())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                Select::make(self::KEY.'.buttons.style')
                    ->label(__('link-page::messages.appearance.buttons_style'))
                    ->options(ButtonStyle::options())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                Select::make(self::KEY.'.buttons.shape')
                    ->label(__('link-page::messages.appearance.buttons_shape'))
                    ->options(ButtonShape::options())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
            ]),
            Fieldset::make(__('link-page::messages.appearance.background'))
                ->visible(fn (Get $get): bool => ! self::usesSiteColors($get))
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Select::make(self::KEY.'.background.type')
                        ->label(__('link-page::messages.appearance.background_type'))
                        ->options(BackgroundType::options())
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    TextInput::make(self::KEY.'.background.angle')
                        ->label(__('link-page::messages.appearance.background_angle'))
                        ->helperText(__('link-page::messages.appearance.background_angle_help'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(Appearance::MAX_ANGLE)
                        ->suffix(__('link-page::messages.appearance.degrees'))
                        ->required()
                        ->live(onBlur: true)
                        ->visible(fn (Get $get): bool => self::backgroundIs($get, BackgroundType::Gradient)),
                    self::image(self::KEY.'.background.image', 'background_image', maxKilobytes: 4096)
                        ->required()
                        ->visible(fn (Get $get): bool => self::backgroundIs($get, BackgroundType::Image))
                        ->columnSpanFull(),
                ]),
            Grid::make(['default' => 1, 'lg' => 2])
                ->visible(fn (Get $get): bool => ! self::usesSiteColors($get))
                ->schema([self::colorSet('light'), self::colorSet('dark')]),
        ];
    }

    private static function colorSet(string $scheme): Fieldset
    {
        $fields = [];
        foreach (self::COLOR_FIELDS as $field) {
            $fields[] = ColorPicker::make(self::KEY.".{$scheme}.{$field}")
                ->label(fn (Get $get): string => self::colorLabel($get, $field))
                ->hex()
                ->regex('/^#[0-9A-Fa-f]{6}\z/')
                ->required()
                ->live(debounce: 400)
                ->visible($field === 'background_to'
                    ? fn (Get $get): bool => self::backgroundIs($get, BackgroundType::Gradient)
                    : true)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::becomeCustom($get, $set));
        }

        $fields[] = TextInput::make(self::KEY.".{$scheme}.overlay")
            ->label(__('link-page::messages.appearance.colors.overlay'))
            ->helperText(__('link-page::messages.appearance.colors.overlay_help'))
            ->integer()
            ->minValue(0)
            ->maxValue(ColorScheme::MAX_OVERLAY)
            ->suffix('%')
            ->required()
            ->live(onBlur: true)
            ->visible(fn (Get $get): bool => self::backgroundIs($get, BackgroundType::Image))
            ->afterStateUpdated(fn (Get $get, Set $set) => self::becomeCustom($get, $set));

        return Fieldset::make(__("link-page::messages.appearance.{$scheme}"))
            ->columns(1)
            ->visible(fn (Get $get): bool => in_array($scheme, self::theme($get)->schemesInUse(), true))
            ->schema($fields);
    }

    /**
     * The images go to the images disk, as every panel upload in the core, with a fresh name.
     * A file of the media library can be chosen too («Elegir de la biblioteca»): the picker
     * offers only images of these types and size, and refuses any other path on saving.
     */
    private static function image(string $name, string $label, int $maxKilobytes): FileUpload
    {
        $upload = FileUpload::make($name)
            ->label(__("link-page::messages.appearance.{$label}"))
            ->helperText(__("link-page::messages.appearance.{$label}_help"))
            ->image()
            ->disk('images')
            ->directory(fn (): string => 'link-page/'.now()->format('Y/m'))
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.self::extension($file)
            )
            ->acceptedFileTypes(self::IMAGE_TYPES)
            ->maxSize($maxKilobytes)
            ->live();

        return MediaLibraryPicker::attachTo($upload, storedPaths: StoredSettingsImages::paths(...));
    }

    /** The extension from the content, so the stored name always matches Appearance's pattern. */
    private static function extension(TemporaryUploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    private static function colorLabel(Get $get, string $field): string
    {
        if ($field === 'background') {
            return match (self::backgroundType($get)) {
                BackgroundType::Gradient => __('link-page::messages.appearance.colors.background_gradient'),
                BackgroundType::Image => __('link-page::messages.appearance.colors.background_image'),
                BackgroundType::Solid => __('link-page::messages.appearance.colors.background'),
            };
        }

        return __("link-page::messages.appearance.colors.{$field}");
    }

    /** A colour changed by hand: the fields no longer are the template's. */
    private static function becomeCustom(Get $get, Set $set): void
    {
        $template = AppearanceTemplate::tryFrom((string) $get(self::KEY.'.template'));

        if ($template !== null && in_array($template, AppearanceTemplate::named(), true)) {
            $set(self::KEY.'.template', AppearanceTemplate::Custom->value);
        }
    }

    private static function usesSiteColors(Get $get): bool
    {
        return AppearanceTemplate::tryFrom((string) $get(self::KEY.'.template'))?->usesSiteColors() ?? true;
    }

    private static function theme(Get $get): ThemeMode
    {
        return ThemeMode::tryFrom((string) $get(self::KEY.'.theme')) ?? ThemeMode::Device;
    }

    private static function backgroundType(Get $get): BackgroundType
    {
        return BackgroundType::tryFrom((string) $get(self::KEY.'.background.type')) ?? BackgroundType::Solid;
    }

    private static function backgroundIs(Get $get, BackgroundType $type): bool
    {
        return self::backgroundType($get) === $type;
    }
}
