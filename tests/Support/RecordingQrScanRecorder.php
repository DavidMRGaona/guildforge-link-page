<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Support;

use Modules\LinkPage\Application\Contracts\QrScanRecorderInterface;

final class RecordingQrScanRecorder implements QrScanRecorderInterface
{
    /** @var list<string> */
    public array $ids = [];

    public function record(string $qrCodeId): void
    {
        $this->ids[] = $qrCodeId;
    }
}
