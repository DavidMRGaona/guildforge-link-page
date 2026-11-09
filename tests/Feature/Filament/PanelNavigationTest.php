<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament;

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Support\PanelNavigationGroup;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Modules\LinkPage\Filament\Pages\LinkPageStatsPage;
use Modules\LinkPage\Filament\Resources\LinkResource;
use Modules\LinkPage\Filament\Resources\QrCodeResource;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The panel menu is grouped by task (core#409): the link page sits in «Sitio web» with the
 * QR codes and the statistics under it, and its settings join the «Configuración» cluster.
 */
final class PanelNavigationTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel(
            [LinkResource::class, QrCodeResource::class],
            [LinkPageSettingsPage::class, LinkPageStatsPage::class],
        );
        $this->actingAsPanelAdmin();
    }

    public function test_the_links_entry_is_the_link_page_parent_in_the_website_group(): void
    {
        $this->assertSame(PanelNavigationGroup::Site->getLabel(), LinkResource::getNavigationGroup());
        $this->assertSame(50, LinkResource::getNavigationSort());
        $this->assertSame('Página de enlaces', LinkResource::getNavigationLabel());
        $this->assertNull(LinkResource::getNavigationParentItem());
    }

    public function test_the_links_resource_keeps_its_own_model_labels(): void
    {
        $this->assertSame('enlace', LinkResource::getModelLabel());
        $this->assertSame('Enlaces propios', LinkResource::getPluralModelLabel());
    }

    public function test_qr_codes_and_statistics_are_children_of_the_link_page(): void
    {
        foreach ([QrCodeResource::class, LinkPageStatsPage::class] as $entry) {
            $this->assertSame(PanelNavigationGroup::Site->getLabel(), $entry::getNavigationGroup(), $entry);
            $this->assertSame(LinkResource::getNavigationLabel(), $entry::getNavigationParentItem(), $entry);
        }

        $this->assertSame('Códigos QR', QrCodeResource::getNavigationLabel());
        $this->assertSame('Estadísticas de enlaces', LinkPageStatsPage::getNavigationLabel());
    }

    public function test_the_settings_join_the_settings_cluster(): void
    {
        $this->assertSame(SettingsCluster::class, LinkPageSettingsPage::getCluster());
        $this->assertSame(150, LinkPageSettingsPage::getNavigationSort());
        $this->assertSame('Página de enlaces', LinkPageSettingsPage::getNavigationLabel());
        $this->assertNull(LinkPageSettingsPage::getNavigationGroup());
        $this->assertSame('Sistema', SettingsCluster::getNavigationGroup());
        $this->assertStringStartsWith('/admin/configuracion/', (string) parse_url(LinkPageSettingsPage::getUrl(), PHP_URL_PATH));
    }

    public function test_no_entry_uses_the_old_core_navigation_keys(): void
    {
        $sources = array_merge(
            glob(dirname(__DIR__, 3).'/src/Filament/*/*.php') ?: [],
            glob(dirname(__DIR__, 3).'/src/Filament/Schemas/*.php') ?: [],
            [dirname(__DIR__, 3).'/src/LinkPageServiceProvider.php'],
        );

        // The glob must really find the pages, resources and schemas, or the loop below proves nothing
        $this->assertGreaterThanOrEqual(5, count($sources));

        foreach ($sources as $file) {
            $this->assertStringNotContainsString('filament.navigation.', (string) file_get_contents($file), $file);
        }
    }

    public function test_the_panel_menu_shows_the_link_page_under_the_website_group(): void
    {
        $this->get(LinkResource::getUrl())
            ->assertOk()
            ->assertSeeInOrder([PanelNavigationGroup::Site->getLabel(), 'Página de enlaces']);
    }
}
