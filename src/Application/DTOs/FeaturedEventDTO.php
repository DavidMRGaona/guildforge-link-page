<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\DTOs;

use DateTimeImmutable;

/**
 * The event of the big block: the core event it opens, when it takes place and the instant its
 * venue day began (so the page can say "today" without working out the venue's calendar).
 */
final readonly class FeaturedEventDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public DateTimeImmutable $dayStartsAt,
        public bool $spansDays,
        public ?string $location,
        public ?string $imagePublicId,
    ) {}
}
