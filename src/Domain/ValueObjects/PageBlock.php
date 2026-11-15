<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

/**
 * One site block placed on the link page (link-page#48): the key of a block some module offers for
 * the `link_page` placement, an optional title drawn above it and the block's own settings, which
 * only the block understands. The page stores a list of them under `blocks`.
 */
final readonly class PageBlock
{
    /** Blocks on the page at most: it is a short page for phones */
    public const int MAX_BLOCKS = 3;

    public const int MAX_TITLE_LENGTH = 80;

    /** The core's limit for a site block key */
    public const int MAX_KEY_LENGTH = 100;

    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $key,
        public ?string $title,
        public array $settings,
    ) {}

    /**
     * The stored list, read tolerantly: anything that is not a list of entries with a key is left
     * out, never an error, and at most MAX_BLOCKS are kept, in their order.
     *
     * @return list<self>
     */
    public static function listFrom(mixed $stored): array
    {
        if (! is_array($stored)) {
            return [];
        }

        $blocks = [];

        foreach ($stored as $entry) {
            $block = is_array($entry) ? self::fromEntry($entry) : null;

            if ($block instanceof self) {
                $blocks[] = $block;
            }

            if (count($blocks) === self::MAX_BLOCKS) {
                break;
            }
        }

        return $blocks;
    }

    /**
     * A title as stored: trimmed and cut, null when blank.
     */
    public static function titleFrom(mixed $title): ?string
    {
        if (! is_string($title) || trim($title) === '') {
            return null;
        }

        return mb_substr(trim($title), 0, self::MAX_TITLE_LENGTH);
    }

    /**
     * @return array{key: string, title: string|null, settings: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'settings' => $this->settings,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $entry
     */
    private static function fromEntry(array $entry): ?self
    {
        $key = $entry['key'] ?? null;

        if (! is_string($key) || trim($key) === '' || mb_strlen($key) > self::MAX_KEY_LENGTH) {
            return null;
        }

        $settings = is_array($entry['settings'] ?? null) ? $entry['settings'] : [];

        return new self(
            trim($key),
            self::titleFrom($entry['title'] ?? null),
            array_filter($settings, is_string(...), ARRAY_FILTER_USE_KEY),
        );
    }
}
