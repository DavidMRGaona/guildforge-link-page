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
 * Which poster is scanned most: the QR codes by scans in the period. Codes switched off or
 * deleted keep the scans they had.
 */
class TopQrCodesWidget extends TableWidget
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
            ->heading(__('link-page::messages.stats.top_qr_codes'))
            ->records(fn (): array => $this->rows())
            ->columns([
                TextColumn::make('name')
                    ->label(__('link-page::messages.stats.columns.qr_code'))
                    ->description(fn (array $record): ?string => $record['state'] === 'active'
                        ? null
                        : __('link-page::messages.stats.qr_'.$record['state']))
                    ->wrap(),
                TextColumn::make('count')
                    ->label(__('link-page::messages.stats.columns.scans'))
                    ->numeric()
                    ->alignEnd(),
            ])
            ->emptyStateHeading(__('link-page::messages.stats.empty'))
            ->emptyStateIcon('heroicon-o-qr-code')
            ->paginated(false);
    }

    /**
     * @return array<string, array{name: string, state: string, count: int}>
     */
    private function rows(): array
    {
        $ranking = app(LinkPageStatsInterface::class)->topQrCodes(StatsPeriod::fromFilters($this->pageFilters), self::LIMIT);
        $labels = app(StatsLabelsInterface::class)->qrCodes(array_column($ranking, 'qrCodeId'));

        $rows = [];
        foreach ($ranking as $row) {
            $label = $labels[$row['qrCodeId']] ?? ['name' => __('link-page::messages.stats.deleted_qr'), 'state' => 'deleted'];
            $rows[$row['qrCodeId']] = ['name' => $label['name'], 'state' => $label['state'], 'count' => $row['count']];
        }

        return $rows;
    }
}
