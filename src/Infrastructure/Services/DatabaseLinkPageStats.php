<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use App\Application\Services\TimezoneServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\LinkPage\Application\Contracts\LinkPageStatsInterface;
use Modules\LinkPage\Application\Contracts\LinkPageStatsRecorderInterface;
use Modules\LinkPage\Application\DTOs\DailyStatsDTO;
use Modules\LinkPage\Application\DTOs\StatsTotalsDTO;
use Modules\LinkPage\Domain\Enums\StatKind;
use Modules\LinkPage\Domain\ValueObjects\ClickTarget;

/**
 * The counters in `link_page_stats`, one row per kind, subject and display day. Each count is
 * one INSERT ... ON CONFLICT DO UPDATE (PostgreSQL and SQLite), so simultaneous requests never
 * lose a count, as in the core's search misses.
 */
final readonly class DatabaseLinkPageStats implements LinkPageStatsInterface, LinkPageStatsRecorderInterface
{
    private const string TABLE = 'link_page_stats';

    public function __construct(private TimezoneServiceInterface $timezone) {}

    public function recordVisit(): void
    {
        $this->increment(StatKind::Visit, '');
    }

    public function recordScan(string $qrCodeId): void
    {
        $this->increment(StatKind::Scan, strtolower($qrCodeId));
    }

    public function recordClick(ClickTarget $target): void
    {
        $this->increment(StatKind::Click, $target->value());
    }

    public function totals(int $days): StatsTotalsDTO
    {
        $totals = DB::table(self::TABLE)
            ->selectRaw('kind, SUM(count) as total')
            ->where('day', '>', $this->dayOffset(-max(1, $days)))
            ->groupBy('kind')
            ->pluck('total', 'kind');

        return new StatsTotalsDTO(
            visits: (int) ($totals[StatKind::Visit->value] ?? 0),
            scans: (int) ($totals[StatKind::Scan->value] ?? 0),
            clicks: (int) ($totals[StatKind::Click->value] ?? 0),
        );
    }

    public function daily(int $days): array
    {
        $days = max(1, $days);
        $rows = DB::table(self::TABLE)
            ->selectRaw('day, kind, SUM(count) as total')
            ->where('day', '>', $this->dayOffset(-$days))
            ->groupBy('day', 'kind')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[substr((string) $row->day, 0, 10)][(string) $row->kind] = (int) $row->total;
        }

        $series = [];
        for ($offset = 1 - $days; $offset <= 0; $offset++) {
            $day = $this->dayOffset($offset);
            $series[] = new DailyStatsDTO(
                day: $day,
                visits: $counts[$day][StatKind::Visit->value] ?? 0,
                scans: $counts[$day][StatKind::Scan->value] ?? 0,
                clicks: $counts[$day][StatKind::Click->value] ?? 0,
            );
        }

        return $series;
    }

    public function topQrCodes(int $days, int $limit): array
    {
        return array_map(
            static fn (array $row): array => ['qrCodeId' => $row['subject'], 'count' => $row['count']],
            $this->top(StatKind::Scan, $days, $limit),
        );
    }

    public function topClicks(int $days, int $limit): array
    {
        $ranking = [];
        foreach ($this->top(StatKind::Click, $days, $limit) as $row) {
            $target = ClickTarget::tryFrom($row['subject']);
            if ($target instanceof ClickTarget) {
                $ranking[] = ['target' => $target, 'count' => $row['count']];
            }
        }

        return $ranking;
    }

    public function prune(int $days): int
    {
        return DB::table(self::TABLE)->where('day', '<', $this->dayOffset(-max(0, $days)))->delete();
    }

    private function increment(StatKind $kind, string $subject): void
    {
        DB::table(self::TABLE)->upsert(
            [[
                'id' => (string) Str::uuid(),
                'kind' => $kind->value,
                'subject' => $subject,
                'day' => $this->dayOffset(0),
                'count' => 1,
            ]],
            ['kind', 'subject', 'day'],
            ['count' => DB::raw(self::TABLE.'.count + 1')],
        );
    }

    /**
     * @return list<array{subject: string, count: int}>
     */
    private function top(StatKind $kind, int $days, int $limit): array
    {
        $rows = DB::table(self::TABLE)
            ->selectRaw('subject, SUM(count) as total')
            ->where('kind', $kind->value)
            ->where('day', '>', $this->dayOffset(-max(1, $days)))
            ->groupBy('subject')
            ->orderByDesc('total')
            ->orderBy('subject')
            ->limit(max(0, $limit))
            ->get();

        return array_values(array_map(
            static fn (object $row): array => ['subject' => (string) $row->subject, 'count' => (int) $row->total],
            $rows->all(),
        ));
    }

    private function dayOffset(int $days): string
    {
        return $this->timezone->today()->modify(sprintf('%+d days', $days))->format('Y-m-d');
    }
}
