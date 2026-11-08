<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Widgets;

use App\Filament\Concerns\ChecksPermissions;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Modules\LinkPage\Application\Contracts\LinkPageStatsInterface;
use Modules\LinkPage\Application\DTOs\DailyStatsDTO;
use Modules\LinkPage\Filament\Pages\LinkPageStatsPage;
use Modules\LinkPage\Filament\Support\StatsPeriod;

/**
 * Visits, scans and clicks of the link page: on the dashboard the last 30 days, on the
 * statistics page the period picked there.
 */
class LinkPageStatsOverviewWidget extends StatsOverviewWidget
{
    use ChecksPermissions;
    use InteractsWithPageFilters;

    protected static ?int $sort = 92;

    public static function canView(): bool
    {
        return self::userCan(LinkPageStatsPage::PERMISSION);
    }

    protected function getHeading(): ?string
    {
        return __('link-page::messages.title');
    }

    protected function getDescription(): ?string
    {
        return __('link-page::messages.stats.last_days', ['days' => StatsPeriod::fromFilters($this->pageFilters)]);
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = app(LinkPageStatsInterface::class);
        $days = StatsPeriod::fromFilters($this->pageFilters);
        $totals = $stats->totals($days);
        $daily = $stats->daily($days);
        $url = LinkPageStatsPage::getUrl();

        return [
            Stat::make(__('link-page::messages.stats.visits'), Number::format($totals->visits, locale: app()->getLocale()))
                ->description(__('link-page::messages.stats.visits_help'))
                ->icon('heroicon-o-eye')
                ->chart(array_map(static fn (DailyStatsDTO $day): float => (float) $day->visits, $daily))
                ->color('primary')
                ->url($url),
            Stat::make(__('link-page::messages.stats.scans'), Number::format($totals->scans, locale: app()->getLocale()))
                ->description(__('link-page::messages.stats.scans_help'))
                ->icon('heroicon-o-qr-code')
                ->chart(array_map(static fn (DailyStatsDTO $day): float => (float) $day->scans, $daily))
                ->color('success')
                ->url($url),
            Stat::make(__('link-page::messages.stats.clicks'), Number::format($totals->clicks, locale: app()->getLocale()))
                ->description(__('link-page::messages.stats.clicks_help'))
                ->icon('heroicon-o-cursor-arrow-rays')
                ->chart(array_map(static fn (DailyStatsDTO $day): float => (float) $day->clicks, $daily))
                ->color('warning')
                ->url($url),
        ];
    }
}
