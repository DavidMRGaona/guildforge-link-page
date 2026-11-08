<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Schemas;

use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Modules\LinkPage\Domain\Enums\FeaturedMode;
use Modules\LinkPage\Domain\Enums\NoticeTone;
use Modules\LinkPage\Domain\ValueObjects\Notice;
use Modules\LinkPage\Domain\ValueObjects\NoticeLink;

/**
 * The notice and the featured event sections of the link page settings. Their state lives under
 * "notice" and "featured".
 */
final class HighlightsSection
{
    /**
     * @param  Closure(): array<string, string>  $tagOptions  slug => name
     * @param  Closure(): array<string, string>  $eventOptions  id => label
     * @return array<int, Section>
     */
    public static function make(Closure $tagOptions, Closure $eventOptions): array
    {
        return [self::notice(), self::featured($tagOptions, $eventOptions)];
    }

    private static function notice(): Section
    {
        return Section::make(__('link-page::messages.notice.section'))
            ->description(__('link-page::messages.notice.section_help'))
            ->columns(2)
            ->schema([
                Toggle::make('notice.enabled')
                    ->label(__('link-page::messages.notice.enabled'))
                    ->helperText(__('link-page::messages.notice.enabled_help'))
                    ->live()
                    ->columnSpanFull(),
                Textarea::make('notice.text')
                    ->label(__('link-page::messages.notice.text'))
                    ->helperText(__('link-page::messages.notice.text_help', ['max' => Notice::MAX_TEXT_LENGTH]))
                    ->maxLength(Notice::MAX_TEXT_LENGTH)
                    ->rows(2)
                    ->required(fn (Get $get): bool => $get('notice.enabled') === true)
                    ->columnSpanFull(),
                Select::make('notice.tone')
                    ->label(__('link-page::messages.notice.tone'))
                    ->helperText(__('link-page::messages.notice.tone_help'))
                    ->options(NoticeTone::options())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('notice.link_url')
                    ->label(__('link-page::messages.notice.link_url'))
                    ->helperText(__('link-page::messages.notice.link_url_help'))
                    ->maxLength(NoticeLink::MAX_URL_LENGTH)
                    ->rules([static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                        if (is_string($value) && trim($value) !== '' && ! NoticeLink::isValidUrl(trim($value))) {
                            $fail(__('link-page::messages.notice.link_url_invalid'));
                        }
                    }]),
                TextInput::make('notice.link_label')
                    ->label(__('link-page::messages.notice.link_label'))
                    ->helperText(__('link-page::messages.notice.link_label_help', ['max' => NoticeLink::MAX_LABEL_LENGTH]))
                    ->maxLength(NoticeLink::MAX_LABEL_LENGTH),
                DateTimePicker::make('notice.starts_at')
                    ->label(__('link-page::messages.notice.starts_at'))
                    ->helperText(__('link-page::messages.notice.starts_at_help'))
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->seconds(false),
                DateTimePicker::make('notice.ends_at')
                    ->label(__('link-page::messages.notice.ends_at'))
                    ->helperText(__('link-page::messages.notice.ends_at_help'))
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->seconds(false)
                    ->after('notice.starts_at')
                    ->validationMessages(['after' => __('link-page::messages.notice.ends_before_start')]),
            ]);
    }

    /**
     * @param  Closure(): array<string, string>  $tagOptions
     * @param  Closure(): array<string, string>  $eventOptions
     */
    private static function featured(Closure $tagOptions, Closure $eventOptions): Section
    {
        return Section::make(__('link-page::messages.featured.section'))
            ->description(__('link-page::messages.featured.section_help'))
            ->columns(2)
            ->schema([
                Select::make('featured.mode')
                    ->label(__('link-page::messages.featured.mode'))
                    ->helperText(__('link-page::messages.featured.mode_help'))
                    ->options(FeaturedMode::options())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->live()
                    ->required()
                    ->columnSpanFull(),
                Select::make('featured.tag')
                    ->label(__('link-page::messages.featured.tag'))
                    ->helperText(__('link-page::messages.featured.tag_help'))
                    ->options($tagOptions)
                    ->native(false)
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('featured.mode') === FeaturedMode::Auto->value)
                    ->columnSpanFull(),
                Select::make('featured.event')
                    ->label(__('link-page::messages.featured.event'))
                    ->helperText(__('link-page::messages.featured.event_help'))
                    ->options($eventOptions)
                    ->native(false)
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('featured.mode') === FeaturedMode::Manual->value)
                    ->required(fn (Get $get): bool => $get('featured.mode') === FeaturedMode::Manual->value)
                    ->columnSpanFull(),
            ]);
    }
}
