<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use Modules\LinkPage\Application\Contracts\QrScanRecorderInterface;

/**
 * Scans are not counted yet: the anonymous daily statistics replace this binding.
 */
final readonly class NullQrScanRecorder implements QrScanRecorderInterface
{
    public function record(string $qrCodeId): void {}
}
