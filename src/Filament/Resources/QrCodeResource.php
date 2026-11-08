<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Resources;

use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\QrCodes\HexColor;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Filament\Resources\BaseResource;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\LinkPage\Application\Contracts\QrCodeImagesInterface;
use Modules\LinkPage\Application\DTOs\QrCodeDesignDTO;
use Modules\LinkPage\Domain\Enums\PrintPaper;
use Modules\LinkPage\Domain\Enums\QrLogoSource;
use Modules\LinkPage\Filament\Resources\QrCodeResource\Pages\CreateQrCode;
use Modules\LinkPage\Filament\Resources\QrCodeResource\Pages\EditQrCode;
use Modules\LinkPage\Filament\Resources\QrCodeResource\Pages\ListQrCodes;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
    ];

    /** For the preview while the code does not exist yet: same length, so the same density. */
    private const string PREVIEW_CODE = '222222';

    private const int PREVIEW_SIZE = 256;

    /** @var array{key: string, uri: string|null}|null */
    private static ?array $preview = null;

    protected static ?string $model = QrCodeModel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?int $navigationSort = 91;

    protected static ?string $slug = 'link-page-qr-codes';

    public static function getNavigationGroup(): string
    {
        return __('filament.navigation.content');
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
                        FileUpload::make('logo_path')
                            ->label(__('link-page::messages.qr_codes.fields.logo'))
                            ->helperText(__('link-page::messages.qr_codes.fields.logo_help'))
                            ->image()
                            ->disk('images')
                            ->directory(fn (): string => QrCodeModel::LOGO_DIRECTORY.'/'.now()->format('Y/m'))
                            // The extension follows the checked content, never the name the browser sent
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.self::LOGO_EXTENSIONS[$file->getMimeType()]
                            )
                            ->acceptedFileTypes(array_keys(self::LOGO_EXTENSIONS))
                            ->maxSize(1024)
                            ->rules(['mimes:png,jpg,jpeg,webp', 'dimensions:max_width=2048,max_height=2048'])
                            ->visible(fn (Get $get): bool => self::logoSource($get) === QrLogoSource::Custom)
                            ->required(fn (Get $get): bool => self::logoSource($get) === QrLogoSource::Custom)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('link-page::messages.qr_codes.sections.preview'))
                    ->description(__('link-page::messages.qr_codes.sections.preview_help'))
                    ->schema([
                        Image::make(
                            fn (Get $get, ?QrCodeModel $record): string => self::previewUri($get, $record) ?? '',
                            __('link-page::messages.qr_codes.preview_alt'),
                        )
                            ->imageSize(200)
                            ->visible(fn (Get $get, ?QrCodeModel $record): bool => self::previewUri($get, $record) !== null),
                        Text::make(__('link-page::messages.qr_codes.preview_unavailable'))
                            ->color('danger')
                            ->visible(fn (Get $get, ?QrCodeModel $record): bool => self::previewUri($get, $record) === null),
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
     * could not read: the field error says why). A logo just uploaded shows once saved.
     */
    private static function previewUri(Get $get, ?QrCodeModel $record): ?string
    {
        $margin = $get('margin');
        // The upload's state is a list of stored paths or files not saved yet
        $uploaded = $get('logo_path');
        $stored = array_values(array_filter(is_array($uploaded) ? $uploaded : [$uploaded], is_string(...)));

        $design = new QrCodeDesignDTO(
            code: $record instanceof QrCodeModel ? $record->code : self::PREVIEW_CODE,
            foregroundColor: (string) $get('foreground_color'),
            backgroundColor: (string) $get('background_color'),
            marginPercent: is_numeric($margin) ? (int) $margin : QrCodeModel::DEFAULT_MARGIN,
            logoSource: self::logoSource($get) ?? QrLogoSource::None,
            logoPath: $stored[0] ?? $record?->logo_path,
        );

        // The image and the notice both ask: draw once per form state
        $key = serialize($design);
        if (self::$preview === null || self::$preview['key'] !== $key) {
            try {
                $uri = app(QrCodeImagesInterface::class)->render($design, self::PREVIEW_SIZE, QrCodeFormat::Svg)->dataUri();
            } catch (Throwable) {
                $uri = null;
            }

            self::$preview = ['key' => $key, 'uri' => $uri];
        }

        return self::$preview['uri'];
    }
}
