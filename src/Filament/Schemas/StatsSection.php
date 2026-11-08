<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;

/**
 * The statistics section of the link page settings: how many days of counts are kept. Its state
 * lives under "stats", in the stored shape.
 */
final class StatsSection
{
    public static function make(): Section
    {
        return Section::make(__('link-page::messages.stats.settings.section'))
            ->description(__('link-page::messages.stats.settings.section_help'))
            ->schema([
                TextInput::make('stats.retention_days')
                    ->label(__('link-page::messages.stats.settings.retention_days'))
                    ->helperText(__('link-page::messages.stats.settings.retention_days_help'))
                    ->integer()
                    ->minValue(LinkPageSettingsInterface::MIN_STATS_RETENTION_DAYS)
                    ->maxValue(LinkPageSettingsInterface::MAX_STATS_RETENTION_DAYS)
                    ->suffix(__('link-page::messages.settings.days'))
                    ->required(),
            ]);
    }

    /**
     * @param  array<array-key, mixed>  $state
     * @return array{retention_days: int}
     */
    public static function toSettings(array $state): array
    {
        $days = $state['retention_days'] ?? null;

        return [
            'retention_days' => is_numeric($days)
                ? max(LinkPageSettingsInterface::MIN_STATS_RETENTION_DAYS, min((int) $days, LinkPageSettingsInterface::MAX_STATS_RETENTION_DAYS))
                : LinkPageSettingsInterface::DEFAULT_STATS_RETENTION_DAYS,
        ];
    }
}
