<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\DTOs;

/**
 * The counts of one display day (`Y-m-d`).
 */
final readonly class DailyStatsDTO
{
    public function __construct(
        public string $day,
        public int $visits,
        public int $scans,
        public int $clicks,
    ) {}
}
