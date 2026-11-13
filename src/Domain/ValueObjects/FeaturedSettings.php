<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

use Modules\LinkPage\Domain\Enums\FeaturedMode;

/**
 * Which event the featured block shows. Reading never fails: anything malformed means no
 * featured event (mode off) or no tag / no event.
 */
final readonly class FeaturedSettings
{
    public function __construct(
        public FeaturedMode $mode,
        public ?string $tagSlug,
        public ?string $eventId,
    ) {}

    /**
     * @param  array<array-key, mixed>  $stored
     */
    public static function fromArray(array $stored): self
    {
        $mode = $stored['mode'] ?? null;
        $tag = $stored['tag'] ?? null;
        $event = $stored['event'] ?? null;

        return new self(
            (is_string($mode) ? FeaturedMode::tryFrom($mode) : null) ?? FeaturedMode::Off,
            is_string($tag) && trim($tag) !== '' ? trim($tag) : null,
            // A malformed id would make PostgreSQL refuse the query
            is_string($event) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $event) === 1 ? $event : null,
        );
    }

    /**
     * @return array{mode: string, tag: string|null, event: string|null}
     */
    public function toArray(): array
    {
        return ['mode' => $this->mode->value, 'tag' => $this->tagSlug, 'event' => $this->eventId];
    }
}
