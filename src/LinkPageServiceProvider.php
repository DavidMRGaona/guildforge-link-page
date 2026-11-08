<?php

declare(strict_types=1);

namespace Modules\LinkPage;

use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Application\Modules\DTOs\PagePrefixDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Modules\ModuleServiceProvider;
use Filament\Pages\Page;
use Filament\Widgets\Widget;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\LinkPage\Application\Contracts\CalendarClickTargetsInterface;
use Modules\LinkPage\Application\Contracts\ClockInterface;
use Modules\LinkPage\Application\Contracts\HighlightsSettingsInterface;
use Modules\LinkPage\Application\Contracts\LinkPageLinksInterface;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\Contracts\LinkPageStatsInterface;
use Modules\LinkPage\Application\Contracts\LinkPageStatsRecorderInterface;
use Modules\LinkPage\Application\Contracts\QrCodeImagesInterface;
use Modules\LinkPage\Application\Contracts\QrScanRecorderInterface;
use Modules\LinkPage\Application\Contracts\SiteSocialProfilesInterface;
use Modules\LinkPage\Application\Contracts\StatsLabelsInterface;
use Modules\LinkPage\Console\Commands\PruneStatsCommand;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Modules\LinkPage\Filament\Pages\LinkPageStatsPage;
use Modules\LinkPage\Filament\Resources\LinkResource;
use Modules\LinkPage\Filament\Resources\QrCodeResource;
use Modules\LinkPage\Filament\Widgets\LinkPageStatsOverviewWidget;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\LinkModel;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Modules\LinkPage\Infrastructure\Services\CoreCalendarClickTargets;
use Modules\LinkPage\Infrastructure\Services\CoreSiteSocialProfiles;
use Modules\LinkPage\Infrastructure\Services\CountingQrScanRecorder;
use Modules\LinkPage\Infrastructure\Services\DatabaseLinkPageStats;
use Modules\LinkPage\Infrastructure\Services\EloquentLinkPageLinks;
use Modules\LinkPage\Infrastructure\Services\HighlightsSettings;
use Modules\LinkPage\Infrastructure\Services\LinkPageSettings;
use Modules\LinkPage\Infrastructure\Services\QrCodeImages;
use Modules\LinkPage\Infrastructure\Services\StatsLabels;
use Modules\LinkPage\Infrastructure\Services\SystemClock;
use Modules\LinkPage\Infrastructure\Stats\TrafficFilter;
use Modules\LinkPage\Policies\LinkPolicy;
use Modules\LinkPage\Policies\QrCodePolicy;

final class LinkPageServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'link-page';
    }

    public function register(): void
    {
        // Loads config/module.php into "modules.link-page" and the effective settings into
        // "modules.settings.link-page"
        parent::register();

        $this->app->bind(LinkPageSettingsInterface::class, LinkPageSettings::class);
        $this->app->bind(LinkPageLinksInterface::class, EloquentLinkPageLinks::class);
        $this->app->bind(SiteSocialProfilesInterface::class, CoreSiteSocialProfiles::class);
        $this->app->bind(HighlightsSettingsInterface::class, HighlightsSettings::class);
        $this->app->bind(ClockInterface::class, SystemClock::class);
        $this->app->bind(QrCodeImagesInterface::class, QrCodeImages::class);
        $this->app->bind(LinkPageStatsRecorderInterface::class, DatabaseLinkPageStats::class);
        $this->app->bind(LinkPageStatsInterface::class, DatabaseLinkPageStats::class);
        $this->app->bind(QrScanRecorderInterface::class, CountingQrScanRecorder::class);
        $this->app->bind(CalendarClickTargetsInterface::class, CoreCalendarClickTargets::class);
        $this->app->bind(StatsLabelsInterface::class, StatsLabels::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The beacon endpoint, per device (keyed hash of address and agent) and, looser, per
        // address alone, so changing the agent on every request does not get round it.
        // Neither key is ever stored with the statistics
        RateLimiter::for('link-page-stats', static fn (Request $request): array => [
            Limit::perMinute(TrafficFilter::BEACONS_PER_MINUTE)->by('link-page-stats:'.TrafficFilter::device($request)),
            Limit::perMinute(TrafficFilter::BEACONS_PER_ADDRESS_MINUTE)->by('link-page-stats-address:'.TrafficFilter::address($request)),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([PruneStatsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('link-page:prune-stats')->dailyAt('03:40')->withoutOverlapping(10);
        });
    }

    /**
     * Stored as "link-page:manage", "link-page:qr_codes.manage", "link-page:links.manage" and
     * "link-page:stats.view". Admins pass every check; other roles only get them when an admin
     * ticks them in the role editor. Only stats.view is suggested for editor (see
     * PermissionSuggestionsTest).
     *
     * @return array<PermissionDTO>
     */
    public function registerPermissions(): array
    {
        return [
            new PermissionDTO(
                name: 'manage',
                label: __('link-page::messages.permissions.manage'),
                group: __('link-page::messages.title'),
                module: $this->moduleName(),
            ),
            new PermissionDTO(
                name: 'qr_codes.manage',
                label: __('link-page::messages.permissions.qr_codes_manage'),
                group: __('link-page::messages.title'),
                module: $this->moduleName(),
            ),
            new PermissionDTO(
                name: 'links.manage',
                label: __('link-page::messages.permissions.links_manage'),
                group: __('link-page::messages.title'),
                module: $this->moduleName(),
            ),
            // Read only, like the core's search.view_stats: suggested for editor, never granted
            new PermissionDTO(
                name: 'stats.view',
                label: __('link-page::messages.permissions.stats_view'),
                group: __('link-page::messages.title'),
                module: $this->moduleName(),
                roles: ['editor'],
            ),
        ];
    }

    /**
     * The page is offered to the site menu.
     *
     * @return array<ModuleRouteDTO>
     */
    public function registerRoutes(): array
    {
        return [
            new ModuleRouteDTO(
                routeName: 'link_page.show',
                label: __('link-page::messages.title'),
                module: $this->moduleName(),
            ),
        ];
    }

    /**
     * Without the core layout: the page has no site header, menu or footer of its own.
     *
     * @return array<PagePrefixDTO>
     */
    public function registerPagePrefixes(): array
    {
        return [
            new PagePrefixDTO(prefix: 'LinkPage', module: $this->moduleName(), usesCoreLayout: false),
        ];
    }

    /**
     * @return array<class-string<Page>>
     */
    public function registerFilamentPages(): array
    {
        return [LinkPageSettingsPage::class, LinkPageStatsPage::class];
    }

    /**
     * The dashboard gets the overview of the last 30 days; the chart and the rankings stay on the
     * statistics page.
     *
     * @return array<class-string<Widget>>
     */
    public function registerFilamentWidgets(): array
    {
        return [LinkPageStatsOverviewWidget::class];
    }

    /**
     * @return array<class-string>
     */
    public function registerFilamentResources(): array
    {
        return [LinkResource::class, QrCodeResource::class];
    }

    /**
     * @return array<class-string, class-string>
     */
    public function registerPolicies(): array
    {
        return [
            LinkModel::class => LinkPolicy::class,
            QrCodeModel::class => QrCodePolicy::class,
        ];
    }
}
