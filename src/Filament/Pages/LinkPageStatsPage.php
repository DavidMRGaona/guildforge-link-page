<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Pages;

use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Concerns\SafeModuleNavigation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Modules\LinkPage\Filament\Support\StatsPeriod;
use Modules\LinkPage\Filament\Widgets\LinkPageDailyChartWidget;
use Modules\LinkPage\Filament\Widgets\LinkPageStatsOverviewWidget;
use Modules\LinkPage\Filament\Widgets\TopLinksWidget;
use Modules\LinkPage\Filament\Widgets\TopQrCodesWidget;

/**
 * The anonymous statistics of /enlaces over a period: the totals, the evolution per day and
 * the rankings of QR codes and links. Open to link-page:view_stats.
 */
final class LinkPageStatsPage extends Page
{
    use ChecksPermissions;
    use HasFiltersForm;
    use HasKnowledgeGuideAction;
    use SafeModuleNavigation;

    public const string PERMISSION = 'link-page:view_stats';

    public const string GUIDE = 'link-page.panel.stats';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 93;

    protected static ?string $slug = 'link-page-stats';

    public static function getNavigationGroup(): string
    {
        return __('filament.navigation.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('link-page::messages.stats.navigation');
    }

    public function getTitle(): string
    {
        return __('link-page::messages.stats.title');
    }

    public static function canAccess(): bool
    {
        return self::userCan(self::PERMISSION);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('days')
                ->label(__('link-page::messages.stats.period'))
                ->options(StatsPeriod::options())
                ->default(StatsPeriod::DEFAULT)
                ->selectablePlaceholder(false)
                ->native(false),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('filtersForm'),
            Grid::make(['default' => 1, 'xl' => 2])
                ->schema(fn (): array => $this->getWidgetsSchemaComponents([
                    LinkPageStatsOverviewWidget::class,
                    LinkPageDailyChartWidget::class,
                    TopQrCodesWidget::class,
                    TopLinksWidget::class,
                ])),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return array_filter([$this->knowledgeGuideAction(self::GUIDE)]);
    }
}
