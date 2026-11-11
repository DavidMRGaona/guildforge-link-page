<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Support;

use App\Application\Media\Services\MediaFileReleaserInterface;

/**
 * Records the files handed to the core instead of checking and deleting them.
 */
final class RecordingMediaFileReleaser implements MediaFileReleaserInterface
{
    /** @var list<string> */
    public array $released = [];

    public function release(string $storedPath): void
    {
        $this->released[] = $storedPath;
    }

    /**
     * Binds a fresh recorder in the container and returns it.
     */
    public static function bind(): self
    {
        $releaser = new self;
        app()->instance(MediaFileReleaserInterface::class, $releaser);

        return $releaser;
    }
}
