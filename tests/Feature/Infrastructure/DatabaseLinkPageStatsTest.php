<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Infrastructure;

use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LinkPage\Application\Contracts\LinkPageStatsInterface;
use Modules\LinkPage\Application\Contracts\LinkPageStatsRecorderInterface;
use Modules\LinkPage\Domain\ValueObjects\ClickTarget;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Anonymous counters per display day: what happened and how often, never who or from where.
 */
final class DatabaseLinkPageStatsTest extends ModuleTestCase
{
    private const string QR = '0199f3a2-7b1c-7d4e-9a10-3c5b2e8f6d41';

    private const string OTHER_QR = '0199f3a2-7b1c-7d4e-9a10-3c5b2e8f6d42';

    private const string LINK = 'link:0199f3a2-7b1c-7d4e-9a10-3c5b2e8f6d43';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.display_timezone' => 'Europe/Madrid']);
        $this->app->forgetInstance(TimezoneServiceInterface::class);
        CarbonImmutable::setTestNow('2026-11-08 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_the_table_holds_only_what_happened_the_day_and_how_often(): void
    {
        $this->assertSame(
            ['count', 'day', 'id', 'kind', 'subject'],
            collect(Schema::getColumnListing('link_page_stats'))->sort()->values()->all(),
        );
    }

    public function test_repeated_events_add_up_in_one_row_per_day(): void
    {
        $recorder = $this->recorder();

        $recorder->recordVisit();
        $recorder->recordVisit();
        $recorder->recordScan(self::QR);
        $recorder->recordClick($this->target(self::LINK));
        $recorder->recordClick($this->target(self::LINK));
        $recorder->recordClick($this->target(self::LINK));

        $rows = DB::table('link_page_stats')->orderBy('kind')->get(['kind', 'subject', 'day', 'count'])
            ->map(fn (object $row): array => (array) $row)->all();

        $this->assertSame([
            ['kind' => 'click', 'subject' => self::LINK, 'day' => '2026-11-08', 'count' => 3],
            ['kind' => 'scan', 'subject' => self::QR, 'day' => '2026-11-08', 'count' => 1],
            ['kind' => 'visit', 'subject' => '', 'day' => '2026-11-08', 'count' => 2],
        ], array_map(fn (array $row): array => [...$row, 'count' => (int) $row['count']], $rows));
    }

    public function test_the_day_is_the_display_day_not_the_storage_day(): void
    {
        // 23:30 UTC is already the next day in Madrid
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-08 23:30:00', 'UTC'));

        $this->recorder()->recordVisit();

        $this->assertSame('2026-11-09', DB::table('link_page_stats')->value('day'));
    }

    public function test_totals_cover_the_last_days_with_today_included(): void
    {
        $this->seedRow('visit', '', '2026-11-08', 5);
        $this->seedRow('visit', '', '2026-10-10', 7);
        // 30 days back from the 8th is the 9th of October: the 9th itself is out
        $this->seedRow('visit', '', '2026-10-09', 100);
        $this->seedRow('scan', self::QR, '2026-11-01', 3);
        $this->seedRow('click', self::LINK, '2026-11-02', 4);

        $totals = $this->stats()->totals(30);

        $this->assertSame([12, 3, 4], [$totals->visits, $totals->scans, $totals->clicks]);
    }

    public function test_the_daily_series_has_every_day_even_without_counts(): void
    {
        $this->seedRow('visit', '', '2026-11-08', 2);
        $this->seedRow('scan', self::QR, '2026-11-06', 1);
        $this->seedRow('scan', self::OTHER_QR, '2026-11-06', 4);

        $daily = $this->stats()->daily(3);

        $this->assertSame(['2026-11-06', '2026-11-07', '2026-11-08'], array_map(fn ($day): string => $day->day, $daily));
        $this->assertSame([0, 0, 2], array_map(fn ($day): int => $day->visits, $daily));
        $this->assertSame([5, 0, 0], array_map(fn ($day): int => $day->scans, $daily));
    }

    public function test_the_rankings_add_up_the_period_most_first(): void
    {
        $this->seedRow('scan', self::QR, '2026-11-08', 2);
        $this->seedRow('scan', self::QR, '2026-11-07', 2);
        $this->seedRow('scan', self::OTHER_QR, '2026-11-08', 9);
        $this->seedRow('click', self::LINK, '2026-11-08', 1);
        $this->seedRow('click', 'social:instagram', '2026-11-08', 6);
        $this->seedRow('click', 'not a target', '2026-11-08', 50);

        $this->assertSame(
            [['qrCodeId' => self::OTHER_QR, 'count' => 9], ['qrCodeId' => self::QR, 'count' => 4]],
            $this->stats()->topQrCodes(30, 10),
        );

        $clicks = $this->stats()->topClicks(30, 10);
        $this->assertSame(['social:instagram', self::LINK], array_map(fn (array $row): string => $row['target']->value(), $clicks));
        $this->assertSame([6, 1], array_column($clicks, 'count'));
    }

    public function test_pruning_drops_the_days_older_than_the_period(): void
    {
        $this->seedRow('visit', '', '2026-11-08', 1);
        $this->seedRow('visit', '', '2025-11-08', 1);
        $this->seedRow('visit', '', '2025-11-07', 1);

        $this->assertSame(1, $this->stats()->prune(365));
        $this->assertSame(['2025-11-08', '2026-11-08'], DB::table('link_page_stats')->orderBy('day')->pluck('day')->all());
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

    private function target(string $value): ClickTarget
    {
        $target = ClickTarget::tryFrom($value);
        $this->assertNotNull($target);

        return $target;
    }

    private function recorder(): LinkPageStatsRecorderInterface
    {
        return $this->app->make(LinkPageStatsRecorderInterface::class);
    }

    private function stats(): LinkPageStatsInterface
    {
        return $this->app->make(LinkPageStatsInterface::class);
    }
}
