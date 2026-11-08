<?php

declare(strict_types=1);

namespace Modules\LinkPage;

use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Application\Modules\DTOs\PagePrefixDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Modules\ModuleServiceProvider;
use Filament\Pages\Page;
use Modules\LinkPage\Application\Contracts\LinkPageLinksInterface;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\Contracts\QrCodeImagesInterface;
use Modules\LinkPage\Application\Contracts\QrScanRecorderInterface;
use Modules\LinkPage\Application\Contracts\SiteSocialProfilesInterface;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Modules\LinkPage\Filament\Resources\LinkResource;
use Modules\LinkPage\Filament\Resources\QrCodeResource;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\LinkModel;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Modules\LinkPage\Infrastructure\Services\CoreSiteSocialProfiles;
use Modules\LinkPage\Infrastructure\Services\EloquentLinkPageLinks;
use Modules\LinkPage\Infrastructure\Services\LinkPageSettings;
use Modules\LinkPage\Infrastructure\Services\NullQrScanRecorder;
use Modules\LinkPage\Infrastructure\Services\QrCodeImages;
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
        $this->app->bind(QrCodeImagesInterface::class, QrCodeImages::class);
        // Scans are counted by the statistics; until then, nothing is stored
        $this->app->bindIf(QrScanRecorderInterface::class, NullQrScanRecorder::class);
    }

    /**
     * Stored as "link-page:manage", "link-page:qr_codes.manage" and "link-page:links.manage".
     * Admins pass every check; other roles only get them when an admin ticks them in the role
     * editor (none is suggested, see PermissionSuggestionsTest).
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
        return [LinkPageSettingsPage::class];
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
