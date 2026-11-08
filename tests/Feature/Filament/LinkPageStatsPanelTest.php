<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\Column;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\LinkPage\Filament\Pages\LinkPageStatsPage;
use Modules\LinkPage\Filament\Support\StatsPeriod;
use Modules\LinkPage\Filament\Widgets\LinkPageDailyChartWidget;
use Modules\LinkPage\Filament\Widgets\LinkPageStatsOverviewWidget;
use Modules\LinkPage\Filament\Widgets\TopLinksWidget;
use Modules\LinkPage\Filament\Widgets\TopQrCodesWidget;
use Modules\LinkPage\LinkPageServiceProvider;
use Modules\LinkPage\Tests\Support\CreatesLinks;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class LinkPageStatsPanelTest extends ModuleTestCase
{
    use CreatesLinks;
    use CreatesQrCodes;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-08 12:00:00', 'UTC'));
        $this->registerOnAdminPanel(pages: [LinkPageStatsPage::class]);
    }

    public function test_the_page_and_the_widget_need_the_statistics_permission(): void
    {
        $this->actingAs($this->userWith(['link-page:view_stats']))
            ->get(LinkPageStatsPage::getUrl())
            ->assertOk();
        $this->assertTrue(LinkPageStatsOverviewWidget::canView());

        $this->actingAs($this->userWith(['link-page:manage']))
            ->get(LinkPageStatsPage::getUrl())
            ->assertForbidden();
        $this->assertFalse(LinkPageStatsOverviewWidget::canView());
    }

    public function test_only_the_overview_goes_to_the_dashboard(): void
    {
        $this->assertSame(
            [LinkPageStatsOverviewWidget::class],
            (new LinkPageServiceProvider($this->app))->registerFilamentWidgets(),
        );
    }

    public function test_the_overview_adds_up_the_last_30_days_on_the_dashboard(): void
    {
        $this->actingAs($this->userWith(['link-page:view_stats']));
        $this->seedRow('visit', '', '2026-11-08', 1200);
        $this->seedRow('visit', '', '2026-10-01', 9);
        $this->seedRow('scan', '0199f3a2-7b1c-7d4e-9a10-3c5b2e8f6d41', '2026-11-01', 34);
        $this->seedRow('click', 'home', '2026-11-02', 56);

        Livewire::test(LinkPageStatsOverviewWidget::class)
            ->assertSee('1.200')
            ->assertDontSee('1.209')
            ->assertSee('34')
            ->assertSee('56')
            ->assertSee('Últimos 30 días');
    }

    public function test_the_period_comes_from_the_page_filter_and_only_from_its_options(): void
    {
        $this->assertSame(7, StatsPeriod::fromFilters(['days' => '7']));
        $this->assertSame(365, StatsPeriod::fromFilters(['days' => 365]));
        $this->assertSame(StatsPeriod::DEFAULT, StatsPeriod::fromFilters(['days' => '100000']));
        $this->assertSame(StatsPeriod::DEFAULT, StatsPeriod::fromFilters(null));
    }

    public function test_the_chart_has_one_point_per_day_of_the_period(): void
    {
        $this->actingAs($this->userWith(['link-page:view_stats']));

        $widget = Livewire::test(LinkPageDailyChartWidget::class, ['pageFilters' => ['days' => '7']])->instance();
        $data = (fn (): array => $this->getData())->call($widget);

        $this->assertCount(7, $data['labels']);
        $this->assertCount(3, $data['datasets']);
        $this->assertSame('08/11', end($data['labels']));
    }

    public function test_the_qr_ranking_names_each_code_and_says_which_no_longer_count(): void
    {
        $this->actingAs($this->userWith(['link-page:view_stats']));
        $poster = $this->qrCode(['name' => 'Shop window poster']);
        $off = $this->qrCode(['name' => 'Old flyer', 'is_active' => false]);
        $gone = $this->qrCode(['name' => 'Lost banner']);
        $gone->delete();
        $this->seedRow('scan', $poster->id, '2026-11-08', 40);
        $this->seedRow('scan', $off->id, '2026-11-07', 12);
        $this->seedRow('scan', $gone->id, '2026-11-06', 3);

        Livewire::test(TopQrCodesWidget::class)
            ->assertSeeInOrder(['Shop window poster', '40', 'Old flyer', 'desactivado', '12', 'Lost banner', 'borrado', '3']);
    }

    public function test_the_link_ranking_names_every_kind_of_target(): void
    {
        $this->actingAs($this->userWith(['link-page:view_stats']));
        $link = $this->link(['title' => 'Join our Discord']);
        $this->seedRow('click', $link->linkId(), '2026-11-08', 9);
        $this->seedRow('click', 'social:instagram', '2026-11-08', 8);
        $this->seedRow('click', 'link:0199f3a2-7b1c-7d4e-9a10-3c5b2e8f6d99', '2026-11-08', 7);
        $this->seedRow('click', 'home', '2026-11-08', 6);
        $this->seedRow('click', 'event:0199f3a2-7b1c-7d4e-9a10-3c5b2e8f6d98', '2026-11-08', 5);
        $this->seedRow('click', 'calendar:game-tables:42', '2026-11-08', 4);

        Livewire::test(TopLinksWidget::class)
            ->assertSeeInOrder([
                'Join our Discord', 'Enlace', '9',
                'Instagram', 'Red social', '8',
                'Enlace borrado', '7',
                'Ir a la web', 'Web', '6',
                'Evento que ya no está', 'Evento', '5',
                'Entrada del calendario', 'Calendario', '4',
            ]);
    }

    public function test_the_rankings_fit_a_phone_with_secondary_columns_from_wider_screens(): void
    {
        $this->actingAs($this->userWith(['link-page:view_stats']));

        $links = Livewire::test(TopLinksWidget::class)->instance()->getTable();
        $this->assertSame('sm', $this->column($links->getColumns(), 'type')->getVisibleFrom());
        $this->assertTrue($this->column($links->getColumns(), 'label')->canWrap());
        $this->assertSame([], $links->getRecordActions());

        $qr = Livewire::test(TopQrCodesWidget::class)->instance()->getTable();
        $this->assertTrue($this->column($qr->getColumns(), 'name')->canWrap());
        $this->assertSame([], $qr->getRecordActions());
    }

    public function test_the_page_shows_an_empty_period_without_failing(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create())
            ->get(LinkPageStatsPage::getUrl())
            ->assertOk();

        // The widgets load after the page, each on its own
        Livewire::test(TopQrCodesWidget::class)->assertSee('Todavía no hay datos en este periodo');
        Livewire::test(TopLinksWidget::class)->assertSee('Todavía no hay datos en este periodo');
        Livewire::test(LinkPageStatsOverviewWidget::class)->assertSee('Visitas');
    }

    /**
     * @param  array<string, Column>  $columns
     */
    private function column(array $columns, string $name): Column
    {
        $this->assertArrayHasKey($name, $columns);

        return $columns[$name];
    }

    private function seedRow(string $kind, string $subject, string $day, int $count): void
    {
        DB::table('link_page_stats')->insert([
            'id' => fake()->uuid(),
            'kind' => $kind,
            'subject' => $subject,
            'day' => $day,
            'count' => $count,
        ]);
    }
}
