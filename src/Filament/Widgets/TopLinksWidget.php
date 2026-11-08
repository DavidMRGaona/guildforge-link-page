<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Widgets;

use App\Filament\Concerns\ChecksPermissions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Modules\LinkPage\Application\Contracts\LinkPageStatsInterface;
use Modules\LinkPage\Application\Contracts\StatsLabelsInterface;
use Modules\LinkPage\Filament\Pages\LinkPageStatsPage;
use Modules\LinkPage\Filament\Support\StatsPeriod;

/**
 * What people press on the page: own links, social icons, event buttons, calendar entries and
 * the link to the site, by clicks in the period.
 */
class TopLinksWidget extends TableWidget
{
    use ChecksPermissions;
    use InteractsWithPageFilters;

    public const int LIMIT = 25;

    public static function canView(): bool
    {
        return self::userCan(LinkPageStatsPage::PERMISSION);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('link-page::messages.stats.top_links'))
            ->records(fn (): array => $this->rows())
            ->columns([
                TextColumn::make('label')
                    ->label(__('link-page::messages.stats.columns.link'))
                    ->wrap(),
                TextColumn::make('type')
                    ->label(__('link-page::messages.stats.columns.type'))
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('sm'),
                TextColumn::make('count')
                    ->label(__('link-page::messages.stats.columns.clicks'))
                    ->numeric()
                    ->alignEnd(),
            ])
            ->emptyStateHeading(__('link-page::messages.stats.empty'))
            ->emptyStateIcon('heroicon-o-cursor-arrow-rays')
            ->paginated(false);
    }

    /**
     * @return array<string, array{label: string, type: string, count: int}>
     */
    private function rows(): array
    {
        $ranking = app(LinkPageStatsInterface::class)->topClicks(StatsPeriod::fromFilters($this->pageFilters), self::LIMIT);
        $labels = app(StatsLabelsInterface::class)->targets(array_column($ranking, 'target'));

        $rows = [];
        foreach ($ranking as $row) {
            $value = $row['target']->value();
            $rows[$value] = [
                'label' => $labels[$value] ?? $value,
                'type' => $row['target']->type->label(),
                'count' => $row['count'],
            ];
        }

        return $rows;
    }
}
