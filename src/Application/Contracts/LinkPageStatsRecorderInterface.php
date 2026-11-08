<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Contracts;

use Modules\LinkPage\Domain\ValueObjects\ClickTarget;

/**
 * Adds one to today's counter (display day). It only learns what happened: whoever calls it
 * has already decided that it counts (no bot, no repeat), and nothing about the person is
 * ever passed in.
 */
interface LinkPageStatsRecorderInterface
{
    public function recordVisit(): void;

    public function recordScan(string $qrCodeId): void;

    public function recordClick(ClickTarget $target): void;
}
