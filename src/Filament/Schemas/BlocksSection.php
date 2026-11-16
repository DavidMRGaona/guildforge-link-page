<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Schemas;

use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Modules\LinkPage\Domain\Enums\BlockPosition;
use Modules\LinkPage\Domain\ValueObjects\PageBlock;
use Modules\LinkPage\Filament\Support\PageBlockCatalog;

/**
 * The «Bloques» section of the link page settings (link-page#48): site blocks other modules offer for
 * the link page (the newsletter subscription, for instance), each with an optional title, its own
 * settings and whether it goes before or after the calendar (link-page#53). Its state lives under
 * "blocks" as Builder items; PageBlockCatalog turns them into the stored list.
 */
final class BlocksSection
{
    /**
     * @param  Closure(): PageBlockCatalog  $catalog  the page's catalog, built once per request
     */
    public static function make(Closure $catalog): Section
    {
        return Section::make(__('link-page::messages.blocks.section'))
            ->description(__('link-page::messages.blocks.section_help', ['max' => PageBlock::MAX_BLOCKS]))
            ->schema([
                Builder::make('blocks')
                    ->hiddenLabel()
                    ->blocks(static fn (): array => self::definitions($catalog()))
                    ->helperText(static fn (): ?string => $catalog()->hasAvailable() ? null : (string) __('link-page::messages.blocks.none_available'))
                    ->addActionLabel(__('link-page::messages.blocks.add'))
                    ->addable(static fn (): bool => $catalog()->hasAvailable())
                    ->maxItems(PageBlock::MAX_BLOCKS)
                    ->blockNumbers(false)
                    ->collapsible()
                    ->reorderable()
                    ->default([]),
            ]);
    }

    /**
     * One definition per block of the placement, available or not, plus one per stored key nobody
     * registers now.
     *
     * @return list<Block>
     */
    private static function definitions(PageBlockCatalog $catalog): array
    {
        $blocks = [];

        foreach ($catalog->definitions() as $key => $block) {
            $blocks[] = $catalog->hasForm($key)
                ? self::definitionFor($catalog, $block)
                : self::preservedDefinition($key, $catalog->label($block), __('link-page::messages.blocks.broken'));
        }

        foreach ($catalog->storedKeys() as $key) {
            if (! array_key_exists($key, $catalog->definitions())) {
                $blocks[] = self::preservedDefinition(
                    $key,
                    __('link-page::messages.blocks.unknown.label', ['key' => $key]),
                    __('link-page::messages.blocks.unknown.help'),
                );
            }
        }

        return $blocks;
    }

    private static function definitionFor(PageBlockCatalog $catalog, SiteBlockInterface $block): Block
    {
        $label = $catalog->label($block);
        $available = $catalog->isAvailable($block->key());

        return Block::make($block->key())
            ->label(static fn (?array $state): string => self::itemLabel($label, $state))
            ->schema([
                Text::make(__('link-page::messages.blocks.unavailable'))
                    ->color('warning')
                    ->visible(! $available),
                self::titleField(),
                self::positionField(),
                Group::make($catalog->settingsSchema($block) ?? [])->statePath('settings'),
            ])
            // Kept where it is stored, but not offered while it cannot be drawn
            ->maxItems($available ? null : 0);
    }

    private static function preservedDefinition(string $key, string $label, string $help): Block
    {
        return Block::make($key)
            ->label($label)
            ->schema([
                Text::make($help)->color('warning'),
                Hidden::make('title'),
                Hidden::make('position'),
                Hidden::make(PageBlockCatalog::STORED_SETTINGS),
            ])
            ->maxItems(0);
    }

    private static function titleField(): TextInput
    {
        return TextInput::make('title')
            ->label(__('link-page::messages.blocks.title'))
            ->helperText(__('link-page::messages.blocks.title_help'))
            ->maxLength(PageBlock::MAX_TITLE_LENGTH)
            ->live(onBlur: true);
    }

    private static function positionField(): Select
    {
        return Select::make('position')
            ->label(__('link-page::messages.blocks.position'))
            ->options(BlockPosition::options())
            ->default(BlockPosition::BeforeCalendar->value)
            ->native(false)
            ->selectablePlaceholder(false)
            ->required();
    }

    /**
     * @param  array<string, mixed>|null  $state
     */
    private static function itemLabel(string $label, ?array $state): string
    {
        $title = is_string($state['title'] ?? null) ? trim($state['title']) : '';

        return $title === '' ? $label : $label.' · '.$title;
    }
}
