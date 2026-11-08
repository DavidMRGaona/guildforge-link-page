<?php

declare(strict_types=1);

namespace Modules\LinkPage;

use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Application\Modules\DTOs\PagePrefixDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Modules\ModuleServiceProvider;
use Filament\Pages\Page;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Modules\LinkPage\Infrastructure\Services\LinkPageSettings;

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
    }

    /**
     * Stored as "link-page:manage". Admins pass every check; other roles only get it when an
     * admin ticks it in the role editor (none is suggested, see PermissionSuggestionsTest).
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
}
