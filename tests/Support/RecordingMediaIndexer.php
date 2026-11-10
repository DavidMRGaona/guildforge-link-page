<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Support;

use App\Application\Media\DTOs\MediaSyncResultDTO;
use App\Application\Media\Services\MediaIndexerInterface;
use DateTimeImmutable;
use LogicException;

/**
 * Remembers which usages were asked to refresh, as «type|record id».
 */
final class RecordingMediaIndexer implements MediaIndexerInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function refresh(string $type, string $recordId): void
    {
        $this->calls[] = $type.'|'.$recordId;
    }

    public function sync(): MediaSyncResultDTO
    {
        throw new LogicException('Not used by these tests.');
    }

    public function lastSyncedAt(): ?DateTimeImmutable
    {
        return null;
    }

    public function lastSyncFailedAt(): ?DateTimeImmutable
    {
        return null;
    }
}
