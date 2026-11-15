<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Support;

use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Application\SiteBlocks\Services\SiteBlockRendererInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use Filament\Schemas\Components\Component;
use JsonException;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Domain\ValueObjects\PageBlock;
use Throwable;

/**
 * What the «Bloques» section of the settings needs (link-page#48), worked out once per request:
 * Filament asks for the Builder's blocks many times, and `available()` asks every module block
 * whether it can be drawn.
 *
 * The definitions come from every valid block of the link page placement, available or not, so a
 * stored block whose provider is down keeps its form and its settings; only the available ones can
 * be added. A stored block without a form (nobody registers its key now, or its settings cannot be
 * built) keeps its title and settings untouched in a hidden field, read for those stored blocks only.
 */
final class PageBlockCatalog
{
    /** Hidden field of a block without a form: its settings, as stored */
    public const string STORED_SETTINGS = 'stored_settings';

    /** @var array<string, SiteBlockInterface>|null */
    private ?array $definitions = null;

    /** @var array<string, bool>|null Whether each definition's settings can be built */
    private ?array $forms = null;

    /** @var list<string>|null */
    private ?array $available = null;

    /** @var list<string>|null */
    private ?array $storedKeys = null;

    /**
     * Every valid block of the link page placement, by key.
     *
     * @return array<string, SiteBlockInterface>
     */
    public function definitions(): array
    {
        if ($this->definitions === null) {
            $this->definitions = [];

            try {
                foreach (resolve(SiteBlockRegistryInterface::class)->forPlacement(SiteBlockPlacement::LinkPage) as $block) {
                    $this->definitions[$block->key()] = $block;
                }
            } catch (Throwable) {
                // A form without blocks is still a form
            }
        }

        return $this->definitions;
    }

    /**
     * Whether the block can be added and drawn now.
     */
    public function isAvailable(string $key): bool
    {
        return in_array($key, $this->available(), true);
    }

    /**
     * Whether any block can be added now.
     */
    public function hasAvailable(): bool
    {
        return $this->available() !== [];
    }

    /**
     * Whether the page shows and saves the settings of this block; without it the stored ones travel
     * untouched in a hidden field.
     */
    public function hasForm(string $key): bool
    {
        if ($this->forms === null) {
            $this->forms = [];

            foreach ($this->definitions() as $definitionKey => $block) {
                $this->forms[$definitionKey] = $this->settingsSchema($block) !== null;
            }
        }

        return $this->forms[$key] ?? false;
    }

    /**
     * Fresh settings fields of a block (one component cannot belong to two places of a form).
     *
     * @return array<Component>|null null when they cannot be built
     */
    public function settingsSchema(SiteBlockInterface $block): ?array
    {
        try {
            return $block->settingsSchema();
        } catch (Throwable) {
            return null;
        }
    }

    public function label(SiteBlockInterface $block): string
    {
        try {
            $label = $block->label();
        } catch (Throwable) {
            return $block->key();
        }

        return trim($label) === '' ? $block->key() : $label;
    }

    /**
     * The keys of the stored blocks, read once per request.
     *
     * @return list<string>
     */
    public function storedKeys(): array
    {
        $this->storedKeys ??= array_values(array_unique(array_map(
            static fn (PageBlock $block): string => $block->key,
            resolve(LinkPageSettingsInterface::class)->blocks(),
        )));

        return $this->storedKeys;
    }

    /**
     * The Builder state of a stored block.
     *
     * @return array{type: string, data: array<string, mixed>}
     */
    public function formState(PageBlock $block): array
    {
        if ($this->hasForm($block->key)) {
            return ['type' => $block->key, 'data' => ['title' => $block->title, 'settings' => $block->settings]];
        }

        try {
            $stored = json_encode($block->settings, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $stored = '{}';
        }

        return ['type' => $block->key, 'data' => ['title' => $block->title, self::STORED_SETTINGS => $stored]];
    }

    /**
     * What is stored from the Builder state: a block with a form keeps its fields (never a hidden copy
     * sent along with them); one without a form keeps its hidden copy, but only when it was stored, so
     * a key forged into the form is dropped.
     *
     * @return list<array{key: string, title: string|null, settings: array<string, mixed>}>
     */
    public function toStored(mixed $items): array
    {
        $blocks = [];

        foreach (is_array($items) ? array_values($items) : [] as $item) {
            $block = $this->blockFrom($item);

            if ($block instanceof PageBlock) {
                $blocks[] = $block->toArray();
            }
        }

        return array_slice($blocks, 0, PageBlock::MAX_BLOCKS);
    }

    private function blockFrom(mixed $item): ?PageBlock
    {
        if (! is_array($item) || ! is_string($item['type'] ?? null) || trim($item['type']) === '') {
            return null;
        }

        $key = $item['type'];
        $data = is_array($item['data'] ?? null) ? $item['data'] : [];
        $title = PageBlock::titleFrom($data['title'] ?? null);

        if (! $this->hasForm($key)) {
            if (! in_array($key, $this->storedKeys(), true)) {
                return null;
            }

            return new PageBlock($key, $title, $this->storedSettings($data[self::STORED_SETTINGS] ?? null));
        }

        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        return new PageBlock($key, $title, array_filter($settings, is_string(...), ARRAY_FILTER_USE_KEY));
    }

    /**
     * @return array<string, mixed>
     */
    private function storedSettings(mixed $json): array
    {
        if (! is_string($json) || $json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? array_filter($decoded, is_string(...), ARRAY_FILTER_USE_KEY) : [];
    }

    /**
     * @return list<string>
     */
    private function available(): array
    {
        if ($this->available === null) {
            try {
                $this->available = array_map(
                    static fn (SiteBlockInterface $block): string => $block->key(),
                    resolve(SiteBlockRendererInterface::class)->available(SiteBlockPlacement::LinkPage),
                );
            } catch (Throwable) {
                $this->available = [];
            }
        }

        return $this->available;
    }
}
