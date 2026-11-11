<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Schemas;

use App\Application\Services\SiteBrandingServiceInterface;
use App\Filament\Forms\Components\MediaLibraryPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Domain\ValueObjects\ShareImagePath;
use Modules\LinkPage\Filament\Support\StoredSettingsImages;

/**
 * The share section of the link page settings: the card WhatsApp, Telegram and the social
 * networks show when the page is shared, with a preview beside it. Its state lives under "share",
 * in the stored shape.
 */
final class ShareSection
{
    private const string KEY = 'share';

    /** JPG and PNG, which every app that builds a preview shows. */
    private const array IMAGE_TYPES = ['image/png', 'image/jpeg'];

    private const int MAX_IMAGE_KILOBYTES = 1024;

    public static function make(): Section
    {
        /** @var view-string $preview */
        $preview = 'link-page::filament.share-preview';

        return Section::make(__('link-page::messages.share.section'))
            ->description(__('link-page::messages.share.section_help'))
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
            TextInput::make(self::KEY.'.title')
                ->label(__('link-page::messages.share.title'))
                ->helperText(__('link-page::messages.share.title_help'))
                ->placeholder(fn (): string => app(SiteBrandingServiceInterface::class)->siteName())
                ->maxLength(LinkPageSettingsInterface::MAX_SHARE_TITLE_LENGTH)
                ->live(onBlur: true),
            Textarea::make(self::KEY.'.description')
                ->label(__('link-page::messages.share.description'))
                ->helperText(__('link-page::messages.share.description_help'))
                ->maxLength(LinkPageSettingsInterface::MAX_SHARE_DESCRIPTION_LENGTH)
                ->rows(3)
                ->live(onBlur: true),
            // The images go to the images disk, as every panel upload in the core, with a fresh
            // name. The core's preview URLs crop them from the centre to 1200 × 630. A file of the
            // media library can be chosen too (only JPG or PNG up to the same size), with no
            // minimum size, as uploads have none
            MediaLibraryPicker::attachTo(FileUpload::make(self::KEY.'.image')
                ->label(__('link-page::messages.share.image'))
                ->helperText(__('link-page::messages.share.image_help'))
                ->image()
                ->disk('images')
                ->directory(fn (): string => ShareImagePath::DIRECTORY.'/'.now()->format('Y/m'))
                ->getUploadedFileNameForStorageUsing(
                    fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.($file->getMimeType() === 'image/png' ? 'png' : 'jpg')
                )
                ->acceptedFileTypes(self::IMAGE_TYPES)
                ->maxSize(self::MAX_IMAGE_KILOBYTES)
                ->live(), storedPaths: StoredSettingsImages::paths(...)),
            Toggle::make(self::KEY.'.noindex')
                ->label(__('link-page::messages.share.noindex'))
                ->helperText(__('link-page::messages.share.noindex_help')),
        ];
    }
}
