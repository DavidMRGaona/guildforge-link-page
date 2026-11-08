<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\DTOs;

/**
 * The counts of a period, every QR code and link added up.
 */
final readonly class StatsTotalsDTO
{
    public function __construct(
        public int $visits,
        public int $scans,
        public int $clicks,
    ) {}
}
