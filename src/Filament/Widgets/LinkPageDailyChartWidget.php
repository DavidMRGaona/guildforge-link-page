<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Widgets;

use App\Filament\Concerns\ChecksPermissions;
use DateTimeImmutable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Modules\LinkPage\Application\Contracts\LinkPageStatsInterface;
use Modules\LinkPage\Application\DTOs\DailyStatsDTO;
use Modules\LinkPage\Filament\Pages\LinkPageStatsPage;
use Modules\LinkPage\Filament\Support\StatsPeriod;

/**
 * The three counters day by day over the period of the statistics page.
 */
class LinkPageDailyChartWidget extends ChartWidget
{
    use ChecksPermissions;
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return self::userCan(LinkPageStatsPage::PERMISSION);
    }

    public function getHeading(): string
    {
        return __('link-page::messages.stats.daily');
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    protected function getData(): array
    {
        $daily = app(LinkPageStatsInterface::class)->daily(StatsPeriod::fromFilters($this->pageFilters));

        return [
            'labels' => array_map(
                static fn (DailyStatsDTO $day): string => (new DateTimeImmutable($day->day))->format('d/m'),
                $daily,
            ),
            'datasets' => [
                $this->dataset(__('link-page::messages.stats.visits'), array_map(static fn (DailyStatsDTO $day): int => $day->visits, $daily), '#3b82f6'),
                $this->dataset(__('link-page::messages.stats.scans'), array_map(static fn (DailyStatsDTO $day): int => $day->scans, $daily), '#16a34a'),
                $this->dataset(__('link-page::messages.stats.clicks'), array_map(static fn (DailyStatsDTO $day): int => $day->clicks, $daily), '#d97706'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'interaction' => ['mode' => 'index', 'intersect' => false],
        ];
    }

    /**
     * @param  list<int>  $data
     * @return array<string, mixed>
     */
    private function dataset(string $label, array $data, string $color): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'borderColor' => $color,
            'backgroundColor' => $color,
            'pointRadius' => count($data) > 31 ? 0 : 3,
            'tension' => 0.25,
        ];
    }
}
