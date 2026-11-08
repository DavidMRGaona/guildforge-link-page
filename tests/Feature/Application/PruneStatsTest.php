<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Application;

use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Console\Commands\PruneStatsCommand;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The statistics keep a number of days (365 unless the page settings say otherwise) and the
 * nightly command deletes the older ones.
 */
final class PruneStatsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        // The module is booted after the console application exists: its command is added by hand
        $this->app->make(Kernel::class)->registerCommand($this->app->make(PruneStatsCommand::class));
        $this->travelTo(CarbonImmutable::parse('2026-11-08 12:00:00', 'UTC'));
        $this->seedDay('2026-11-08');
        $this->seedDay('2025-11-08');
        $this->seedDay('2025-11-07');
        $this->seedDay('2026-10-01');
    }

    public function test_it_keeps_365_days_by_default(): void
    {
        $this->assertSame(365, app(LinkPageSettingsInterface::class)->statsRetentionDays());

        $this->artisanCommand('link-page:prune-stats')->expectsOutput('Pruned: 1')->assertSuccessful()->run();

        $this->assertSame(['2025-11-08', '2026-10-01', '2026-11-08'], $this->days());
    }

    public function test_the_retention_comes_from_the_settings_within_its_bounds(): void
    {
        config()->set('modules.settings.link-page.stats.retention_days', 30);
        $this->assertSame(30, app(LinkPageSettingsInterface::class)->statsRetentionDays());

        $this->artisanCommand('link-page:prune-stats')->assertSuccessful()->run();
        $this->assertSame(['2026-11-08'], $this->days());

        config()->set('modules.settings.link-page.stats.retention_days', 5);
        $this->assertSame(LinkPageSettingsInterface::DEFAULT_STATS_RETENTION_DAYS, app(LinkPageSettingsInterface::class)->statsRetentionDays());

        config()->set('modules.settings.link-page.stats.retention_days', 999999);
        $this->assertSame(LinkPageSettingsInterface::MAX_STATS_RETENTION_DAYS, app(LinkPageSettingsInterface::class)->statsRetentionDays());
    }

    public function test_the_days_option_overrides_the_settings_and_must_be_a_number(): void
    {
        $this->artisanCommand('link-page:prune-stats', ['--days' => '0'])->assertSuccessful()->run();
        $this->assertSame(['2026-11-08'], $this->days());

        $this->artisanCommand('link-page:prune-stats', ['--days' => 'all'])->assertFailed()->run();
    }

    public function test_it_runs_every_night(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'link-page:prune-stats'));

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertNotNull($event);
        $this->assertSame('40 3 * * *', $event->expression);
    }

    public function test_the_page_settings_save_the_retention(): void
    {
        $this->registerOnAdminPanel(pages: [LinkPageSettingsPage::class]);
        $this->actingAsPanelAdmin();

        Livewire::test(LinkPageSettingsPage::class)
            ->assertFormSet(['stats.retention_days' => 365])
            ->fillForm(['stats.retention_days' => 10])
            ->call('save')
            ->assertHasFormErrors(['stats.retention_days'])
            ->fillForm(['stats.retention_days' => 90])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['retention_days' => 90], app(ModuleSettingsStoreInterface::class)->stored('link-page')['stats'] ?? null);
    }

    private function seedDay(string $day): void
    {
        DB::table('link_page_stats')->insert(['id' => fake()->uuid(), 'kind' => 'visit', 'subject' => '', 'day' => $day, 'count' => 1]);
    }

    /**
     * @return list<string>
     */
    private function days(): array
    {
        return array_values(DB::table('link_page_stats')->orderBy('day')->pluck('day')->map(fn ($day): string => substr((string) $day, 0, 10))->all());
    }
}
