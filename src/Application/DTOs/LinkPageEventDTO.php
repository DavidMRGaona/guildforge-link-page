<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\DTOs;

use DateTimeImmutable;

/**
 * One event button of the page: the core event it opens and when it takes place.
 */
final readonly class LinkPageEventDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public ?string $location,
        public bool $pinned,
    ) {}
}
