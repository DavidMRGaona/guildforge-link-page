<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Modules\LinkPage\Application\Contracts\LinkPageStatsRecorderInterface;
use Modules\LinkPage\Application\Contracts\QrScanRecorderInterface;
use Modules\LinkPage\Domain\Enums\StatKind;
use Modules\LinkPage\Infrastructure\Stats\TrafficFilter;

/**
 * Counts a scan of an active code unless the traffic filter leaves it out (an automated agent,
 * the same device again within seconds, or too many scans from it).
 */
final readonly class CountingQrScanRecorder implements QrScanRecorderInterface
{
    public function __construct(
        private Container $container,
        private TrafficFilter $filter,
        private LinkPageStatsRecorderInterface $stats,
    ) {}

    public function record(string $qrCodeId): void
    {
        // Read now, not when built: the router keeps the controller (and so this recorder)
        // between requests of the same process
        $request = $this->container->make(Request::class);

        if ($this->filter->shouldCount($request, StatKind::Scan, strtolower($qrCodeId))) {
            $this->stats->recordScan($qrCodeId);
        }
    }
}
