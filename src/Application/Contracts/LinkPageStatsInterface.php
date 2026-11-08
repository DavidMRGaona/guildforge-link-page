<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Contracts;

use Modules\LinkPage\Application\DTOs\DailyStatsDTO;
use Modules\LinkPage\Application\DTOs\StatsTotalsDTO;
use Modules\LinkPage\Domain\ValueObjects\ClickTarget;

/**
 * The counters as the panel reads them. A period of N days is today (display day) and the
 * N - 1 days before it.
 */
interface LinkPageStatsInterface
{
    public function totals(int $days): StatsTotalsDTO;

    /**
     * Every day of the period, oldest first, with zeros on days without counts.
     *
     * @return list<DailyStatsDTO>
     */
    public function daily(int $days): array;

    /**
     * Scans per QR code, most first.
     *
     * @return list<array{qrCodeId: string, count: int}>
     */
    public function topQrCodes(int $days, int $limit): array;

    /**
     * Clicks per link, most first. A stored subject that is no longer a valid id is skipped.
     *
     * @return list<array{target: ClickTarget, count: int}>
     */
    public function topClicks(int $days, int $limit): array;

    /**
     * Deletes the days before the last `$days` and returns how many rows went.
     */
    public function prune(int $days): int;
}
