<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Resources;

use App\Application\QrCodes\DTOs\QrCodeLogoDTO;
use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\QrCodes\HexColor;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Filament\Actions\RowDetailsAction;
use App\Filament\Forms\Components\MediaLibraryPicker;
use App\Filament\Resources\BaseResource;
use App\Filament\Support\PanelNavigationGroup;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\LinkPage\Application\Contracts\QrCodeImagesInterface;
use Modules\LinkPage\Application\DTOs\QrCodeDesignDTO;
use Modules\LinkPage\Domain\Enums\PrintPaper;
use Modules\LinkPage\Domain\Enums\QrLogoSource;
use Modules\LinkPage\Domain\ValueObjects\QrLogoPath;
use Modules\LinkPage\Filament\Resources\QrCodeResource\Pages\CreateQrCode;
use Modules\LinkPage\Filament\Resources\QrCodeResource\Pages\EditQrCode;
use Modules\LinkPage\Filament\Resources\QrCodeResource\Pages\ListQrCodes;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use WeakMap;

/**
 * The printed origins of the link page. Each one has its stable /qr/{code}; disabling or
 * deleting one never breaks a poster (QrRedirectController).
 */
final class QrCodeResource extends BaseResource
{
    private const string HEX_PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    /** The logos the core renderer takes, by MIME type, with the extension they are stored with. */
    private const array LOGO_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        QrCodeLogoDTO::SVG_MIME_TYPE => 'svg',
    ];

    private const int LOGO_MAX_KILOBYTES = 1024;

    private const int LOGO_MAX_PIXELS = 2048;

    /** For the preview while the code does not exist yet: same length, so the same density. */
    private const string PREVIEW_CODE = '222222';

    private const int PREVIEW_SIZE = 256;

    /**
     * What the preview drew, per form instance: Livewire builds a new one on each request, so
     * nothing outlives the request (the site logo may change between two).
     *
     * @var WeakMap<Component, array{preview?: array{key: string, uri: string|null}, siteLogoUnusable?: bool}>|null
     */
    private static ?WeakMap $drawn = null;

    protected static ?string $model = QrCodeModel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    // Children sort only among themselves, under the link page entry
    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'link-page-qr-codes';

    public static function getNavigationGroup(): string
    {
        return PanelNavigationGroup::Site->getLabel();
    }

    public static function getNavigationParentItem(): string
    {
        return LinkResource::getNavigationLabel();
    }

    public static function getNavigationLabel(): string
    {
        return __('link-page::messages.qr_codes.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('link-page::messages.qr_codes.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('link-page::messages.qr_codes.model.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('link-page::messages.qr_codes.sections.origin'))
                    ->description(__('link-page::messages.qr_codes.sections.origin_help'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('link-page::messages.qr_codes.fields.name'))
                            ->helperText(__('link-page::messages.qr_codes.fields.name_help'))
                            ->required()
                            ->maxLength(100),
                        TextInput::make('tagline')
                            ->label(__('link-page::messages.qr_codes.fields.tagline'))
                            ->helperText(__('link-page::messages.qr_codes.fields.tagline_help'))
                            ->placeholder(__('link-page::messages.qr_codes.default_tagline'))
                            ->maxLength(150),
                        Toggle::make('is_active')
                            ->label(__('link-page::messages.qr_codes.fields.is_active'))
                            ->helperText(__('link-page::messages.qr_codes.fields.is_active_help'))
                            ->default(true),
                    ]),
                Section::make(__('link-page::messages.qr_codes.sections.design'))
                    ->description(__('link-page::messages.qr_codes.sections.design_help'))
                    ->columns(['md' => 2])
                    ->schema([
                        ColorPicker::make('foreground_color')
                            ->label(__('link-page::messages.qr_codes.fields.foreground_color'))
                            ->hex()
                            ->default('#000000')
                            ->required()
                            ->regex(self::HEX_PATTERN)
                            ->live(onBlur: true),
                        ColorPicker::make('background_color')
                            ->label(__('link-page::messages.qr_codes.fields.background_color'))
                            ->hex()
                            ->default('#FFFFFF')
                            ->required()
                            ->regex(self::HEX_PATTERN)
                            ->rules([self::readableColours(...)])
                            ->live(onBlur: true),
                        TextInput::make('margin')
                            ->label(__('link-page::messages.qr_codes.fields.margin'))
                            ->helperText(__('link-page::messages.qr_codes.fields.margin_help'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(QrCodeImagesInterface::MAX_MARGIN_PERCENT)
                            ->suffix('%')
                            ->default(QrCodeModel::DEFAULT_MARGIN)
                            ->required()
                            ->live(onBlur: true),
                        Select::make('logo_source')
                            ->label(__('link-page::messages.qr_codes.fields.logo_source'))
                            ->helperText(fn (): string => app(SiteBrandingServiceInterface::class)->customLogoPath() === null
                                ? __('link-page::messages.qr_codes.fields.logo_source_no_site_logo')
                                : __('link-page::messages.qr_codes.fields.logo_source_help'))
                            ->options(QrLogoSource::options())
                            ->enum(QrLogoSource::class)
                            ->default(QrLogoSource::None->value)
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->required()
                            ->live(),
                        MediaLibraryPicker::attachTo(FileUpload::make('logo_path')
                            ->label(__('link-page::messages.qr_codes.fields.logo'))
                            ->helperText(__('link-page::messages.qr_codes.fields.logo_help'))
                            ->image()
                            ->disk('images')
                            ->directory(fn (): string => QrCodeModel::LOGO_DIRECTORY.'/'.now()->format('Y/m'))
                            // The extension follows the checked content, never the name the browser sent
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.self::LOGO_EXTENSIONS[$file->getMimeType()]
                            )
                            // An SVG is kept sanitized: the images disk is public
                            ->saveUploadedFileUsing(self::storeLogo(...))
                            ->acceptedFileTypes(array_keys(self::LOGO_EXTENSIONS))
                            ->maxSize(self::LOGO_MAX_KILOBYTES)
                            // Laravel's dimensions rule lets an SVG through: the core checks its markup
                            ->rules([
                                'mimes:png,jpg,jpeg,webp,svg',
                                'dimensions:max_width='.self::LOGO_MAX_PIXELS.',max_height='.self::LOGO_MAX_PIXELS,
                                self::usableSvgLogo(...),
                            ])
                            ->visible(fn (Get $get): bool => self::logoSource($get) === QrLogoSource::Custom)
                            ->required(fn (Get $get): bool => self::logoSource($get) === QrLogoSource::Custom)
                            // The preview draws an upload as soon as it arrives, before saving
                            ->live()
                            ->columnSpanFull(),
                            // The logo's own rules (sanitized SVG, size) only run on uploads: only
                            // logos uploaded here, by this or another code, can be chosen
                            constrain: static fn (Builder $query): Builder => $query->where('media_files.folder', 'like', QrLogoPath::DIRECTORY.'/%'),
                        ),
                    ]),
                Section::make(__('link-page::messages.qr_codes.sections.preview'))
                    ->description(__('link-page::messages.qr_codes.sections.preview_help'))
                    ->schema([
                        Image::make(
                            fn (Get $get, ?QrCodeModel $record, Component $livewire): string => self::previewUri($get, $record, $livewire) ?? '',
                            __('link-page::messages.qr_codes.preview_alt'),
                        )
                            ->imageSize(200)
                            ->visible(fn (Get $get, ?QrCodeModel $record, Component $livewire): bool => self::previewUri($get, $record, $livewire) !== null),
                        Text::make(__('link-page::messages.qr_codes.preview_unavailable'))
                            ->color('danger')
                            ->visible(fn (Get $get, ?QrCodeModel $record, Component $livewire): bool => self::previewUri($get, $record, $livewire) === null),
                        Text::make(__('link-page::messages.qr_codes.preview_logo_unusable'))
                            ->color('warning')
                            ->visible(fn (Get $get): bool => self::pendingLogoIsUnusable($get)),
                        Text::make(__('link-page::messages.qr_codes.preview_site_logo_unusable'))
                            ->color('warning')
                            ->visible(fn (Get $get, Component $livewire): bool => self::logoSource($get) === QrLogoSource::Site
                                && self::siteLogoIsUnusable($livewire)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('link-page::messages.qr_codes.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('code')
                    ->label(__('link-page::messages.qr_codes.fields.code'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->copyable()
                    ->copyableState(fn (QrCodeModel $record): string => app(QrCodeImagesInterface::class)->url($record->code))
                    ->copyMessage(__('link-page::messages.qr_codes.address_copied')),
                IconColumn::make('is_active')
                    ->label(__('link-page::messages.qr_codes.fields.is_active'))
                    ->boolean()
                    ->visibleFrom('sm'),
                TextColumn::make('updated_at')
                    ->label(__('link-page::messages.qr_codes.fields.updated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('link-page::messages.qr_codes.fields.is_active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    RowDetailsAction::make(),
                    EditAction::make(),
                    self::downloadAction(),
                    self::printAction(PrintPaper::A4),
                    self::printAction(PrintPaper::A5),
                    Action::make('deactivate')
                        ->label(__('link-page::messages.qr_codes.actions.deactivate'))
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription(__('link-page::messages.qr_codes.actions.deactivate_description'))
                        ->visible(fn (QrCodeModel $record): bool => $record->is_active && self::canEdit($record))
                        ->action(fn (QrCodeModel $record) => self::setActive($record, false)),
                    Action::make('activate')
                        ->label(__('link-page::messages.qr_codes.actions.activate'))
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->visible(fn (QrCodeModel $record): bool => ! $record->is_active && self::canEdit($record))
                        ->action(fn (QrCodeModel $record) => self::setActive($record, true)),
                    self::deleteAction(DeleteAction::make()),
                ]),
            ]);
    }

    public static function downloadAction(): Action
    {
        return Action::make('download')
            ->label(__('link-page::messages.qr_codes.actions.download'))
            ->icon('heroicon-o-arrow-down-tray')
            ->modalHeading(fn (QrCodeModel $record): string => __('link-page::messages.qr_codes.actions.download_heading', ['name' => $record->name]))
            ->modalSubmitActionLabel(__('link-page::messages.qr_codes.actions.download'))
            ->schema([
                Select::make('format')
                    ->label(__('link-page::messages.qr_codes.fields.format'))
                    ->options(self::downloadFormats())
                    ->default('png-1024')
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->authorize(fn (QrCodeModel $record): bool => self::canView($record))
            ->action(fn (QrCodeModel $record, array $data): ?StreamedResponse => self::download($record, (string) ($data['format'] ?? '')));
    }

    public static function printAction(PrintPaper $paper): Action
    {
        return Action::make('print_'.$paper->value)
            ->label(__('link-page::messages.qr_codes.actions.print', ['paper' => $paper->label()]))
            ->icon('heroicon-o-printer')
            ->url(fn (QrCodeModel $record): string => route('link_page.qr_codes.print', [$record, $paper->value]), shouldOpenInNewTab: true)
            ->authorize(fn (QrCodeModel $record): bool => self::canView($record));
    }

    /**
     * Irreversible: a modal and a box to tick that says what it means.
     */
    public static function deleteAction(DeleteAction $action): DeleteAction
    {
        return $action
            ->modalDescription(__('link-page::messages.qr_codes.actions.delete_description'))
            ->schema([
                Checkbox::make('confirmation')
                    ->label(__('link-page::messages.qr_codes.actions.delete_confirmation'))
                    ->accepted()
                    ->required(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQrCodes::route('/'),
            'create' => CreateQrCode::route('/create'),
            'edit' => EditQrCode::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function downloadFormats(): array
    {
        $formats = ['svg' => __('link-page::messages.qr_codes.formats.svg')];
        foreach (QrCodeImagesInterface::PNG_SIZES as $size) {
            $formats['png-'.$size] = __('link-page::messages.qr_codes.formats.png', ['size' => $size]);
        }

        return $formats;
    }

    private static function download(QrCodeModel $record, string $format): ?StreamedResponse
    {
        abort_unless(array_key_exists($format, self::downloadFormats()), 422);

        $isSvg = $format === 'svg';
        // SVG scales without loss: the size only sets the grid of the drawing
        $size = $isSvg ? 1024 : (int) Str::after($format, 'png-');

        try {
            $image = app(QrCodeImagesInterface::class)->render(
                $record->toDesign(),
                $size,
                $isSvg ? QrCodeFormat::Svg : QrCodeFormat::Png,
            );
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title(__('link-page::messages.qr_codes.notifications.render_failed'))->danger()->send();

            return null;
        }

        $filename = 'qr-'.$record->code.($isSvg ? '' : '-'.$size).'.'.$image->extension();

        return response()->streamDownload(
            function () use ($image): void {
                echo $image->contents;
            },
            $filename,
            ['Content-Type' => $image->mimeType()],
        );
    }

    private static function setActive(QrCodeModel $record, bool $active): void
    {
        $record->update(['is_active' => $active]);

        Notification::make()
            ->title(__($active ? 'link-page::messages.qr_codes.notifications.activated' : 'link-page::messages.qr_codes.notifications.deactivated'))
            ->success()
            ->send();
    }

    /**
     * The same rules as the core renderer, with the reason in words: phones read dark
     * modules on a light background with enough contrast.
     */
    private static function readableColours(Get $get): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $foreground = $get('foreground_color');
            if (! is_string($value) || ! is_string($foreground)
                || preg_match(self::HEX_PATTERN, $value) !== 1 || preg_match(self::HEX_PATTERN, $foreground) !== 1) {
                return;
            }

            $front = HexColor::fromString($foreground);
            $back = HexColor::fromString($value);

            if ($front->relativeLuminance() >= $back->relativeLuminance()) {
                $fail(__('link-page::messages.qr_codes.validation.foreground_darker'));

                return;
            }

            $ratio = $front->contrastRatio($back);
            if ($ratio < QrCodeOptionsDTO::MIN_CONTRAST_RATIO) {
                $fail(__('link-page::messages.qr_codes.validation.contrast', [
                    'ratio' => number_format($ratio, 2, ',', ''),
                    'minimum' => number_format(QrCodeOptionsDTO::MIN_CONTRAST_RATIO, 1, ',', ''),
                ]));
            }
        };
    }

    private static function logoSource(Get $get): ?QrLogoSource
    {
        $source = $get('logo_source');

        if ($source instanceof QrLogoSource) {
            return $source;
        }

        return is_string($source) ? QrLogoSource::tryFrom($source) : null;
    }

    /**
     * An SVG of what the form holds now, or null when it cannot be drawn (colours a phone
     * could not read: the field error says why). A logo just uploaded is drawn from the
     * upload itself, before anything is stored.
     */
    private static function previewUri(Get $get, ?QrCodeModel $record, Component $livewire): ?string
    {
        $margin = $get('margin');
        $pending = self::pendingLogo($get);

        $design = new QrCodeDesignDTO(
            code: $record instanceof QrCodeModel ? $record->code : self::PREVIEW_CODE,
            foregroundColor: (string) $get('foreground_color'),
            backgroundColor: (string) $get('background_color'),
            marginPercent: is_numeric($margin) ? (int) $margin : QrCodeModel::DEFAULT_MARGIN,
            logoSource: self::logoSource($get) ?? QrLogoSource::None,
            // A new upload replaces the stored logo, even when it cannot be used (then: none)
            logoPath: $pending instanceof TemporaryUploadedFile ? null : (self::storedLogos($get)[0] ?? $record?->logo_path),
            logoContents: $pending instanceof TemporaryUploadedFile ? self::usableLogo($pending)?->contents : null,
        );

        // The image and the notice both ask: draw once per form state
        $drawn = self::drawn($livewire);
        $key = serialize($design);
        if (! isset($drawn['preview']) || $drawn['preview']['key'] !== $key) {
            try {
                $uri = app(QrCodeImagesInterface::class)->render($design, self::PREVIEW_SIZE, QrCodeFormat::Svg)->dataUri();
            } catch (Throwable) {
                $uri = null;
            }

            $drawn['preview'] = ['key' => $key, 'uri' => $uri];
            self::remember($livewire, $drawn);
        }

        return $drawn['preview']['uri'];
    }

    private static function siteLogoIsUnusable(Component $livewire): bool
    {
        $drawn = self::drawn($livewire);
        if (! isset($drawn['siteLogoUnusable'])) {
            $drawn['siteLogoUnusable'] = app(QrCodeImagesInterface::class)->siteLogoIsUnusable();
            self::remember($livewire, $drawn);
        }

        return $drawn['siteLogoUnusable'];
    }

    /**
     * @return array{preview?: array{key: string, uri: string|null}, siteLogoUnusable?: bool}
     */
    private static function drawn(Component $livewire): array
    {
        return self::$drawn[$livewire] ?? [];
    }

    /**
     * @param  array{preview?: array{key: string, uri: string|null}, siteLogoUnusable?: bool}  $drawn
     */
    private static function remember(Component $livewire, array $drawn): void
    {
        self::$drawn ??= new WeakMap;
        self::$drawn[$livewire] = $drawn;
    }

    /**
     * The upload's state is a list of stored paths, files not saved yet or both.
     *
     * @return list<mixed>
     */
    private static function logoUploads(Get $get): array
    {
        $uploaded = $get('logo_path');

        return array_values(is_array($uploaded) ? $uploaded : [$uploaded]);
    }

    /**
     * @return list<string>
     */
    private static function storedLogos(Get $get): array
    {
        return array_values(array_filter(self::logoUploads($get), is_string(...)));
    }

    /**
     * A logo uploaded in this form and not stored yet, when the form uses an uploaded logo.
     */
    private static function pendingLogo(Get $get): ?TemporaryUploadedFile
    {
        if (self::logoSource($get) !== QrLogoSource::Custom) {
            return null;
        }

        $pending = array_find(self::logoUploads($get), fn (mixed $upload): bool => $upload instanceof TemporaryUploadedFile);

        return $pending instanceof TemporaryUploadedFile ? $pending : null;
    }

    private static function pendingLogoIsUnusable(Get $get): bool
    {
        $pending = self::pendingLogo($get);

        return $pending instanceof TemporaryUploadedFile && ! self::usableLogo($pending) instanceof QrCodeLogoDTO;
    }

    /**
     * The upload as the core renderer takes it, when saving would accept it: a type the field
     * takes (from the content), 1 MB, and what the core checks (2048 × 2048 px for a raster
     * image, the markup of an SVG, which it sanitizes). Null for anything else, so the
     * preview never draws a logo the form would then reject.
     */
    private static function usableLogo(TemporaryUploadedFile $file): ?QrCodeLogoDTO
    {
        try {
            $mimeType = $file->getMimeType();
            if (! array_key_exists($mimeType, self::LOGO_EXTENSIONS) || $file->getSize() > self::LOGO_MAX_KILOBYTES * 1024) {
                return null;
            }

            $contents = $file->get();
            $logo = is_string($contents) ? new QrCodeLogoDTO($contents) : null;
        } catch (Throwable) {
            // The temporary file is gone or unreadable, or the core rejects it
            return null;
        }

        // The stored extension follows the detected type: the core must read the same one
        return $logo?->mimeType === $mimeType ? $logo : null;
    }

    /**
     * Rejects, on saving, an SVG the core would not draw. Other types and sizes have their
     * own rules, so each problem is said once.
     */
    private static function usableSvgLogo(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            try {
                $isSvg = $value instanceof TemporaryUploadedFile
                    && $value->getMimeType() === QrCodeLogoDTO::SVG_MIME_TYPE
                    && $value->getSize() <= self::LOGO_MAX_KILOBYTES * 1024;
            } catch (Throwable) {
                return;
            }

            if ($isSvg && ! self::usableLogo($value) instanceof QrCodeLogoDTO) {
                $fail(__('link-page::messages.qr_codes.validation.logo_unusable'));
            }
        };
    }

    /**
     * Stores what the core read: the bytes of a raster image as uploaded, an SVG sanitized.
     */
    private static function storeLogo(BaseFileUpload $component, TemporaryUploadedFile $file): ?string
    {
        $logo = self::usableLogo($file);
        if (! $logo instanceof QrCodeLogoDTO) {
            // The field's rules rejected it: nothing is kept
            return null;
        }

        $path = trim($component->getDirectory().'/'.$component->getUploadedFileNameForStorage($file), '/');
        $component->getDisk()->put($path, $logo->contents, [
            'visibility' => $component->getVisibility(),
            'mimetype' => $logo->mimeType,
        ]);

        return $path;
    }
}
