<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Services;

use App\Application\SiteBlocks\DTOs\ResolvedSiteBlockDTO;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Application\SiteBlocks\Services\SiteBlockRendererInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\LinkPage\Domain\ValueObjects\PageBlock;
use Throwable;

/**
 * Asks the core to resolve the site blocks of the link page for one person (link-page#48). The core
 * decides whether each block can be drawn now (its module on, its provider answering, offered for
 * the link page) and what its component receives; this only fails closed, block by block: anything
 * unexpected is that block left out.
 */
final readonly class PageBlocksResolver
{
    public function __construct(
        private SiteBlockRendererInterface $renderer,
        private SiteBlockRegistryInterface $registry,
    ) {}

    /**
     * @param  list<PageBlock>  $blocks
     * @return list<array{title: string|null, label: string|null, position: string, block: array<string, mixed>}>
     */
    public function resolve(array $blocks, ?Authenticatable $user): array
    {
        $resolved = [];

        foreach ($blocks as $block) {
            try {
                $drawn = $this->renderer->resolveBlock($block->key, $block->settings, SiteBlockPlacement::LinkPage, $user);
            } catch (Throwable) {
                continue;
            }

            if ($drawn instanceof ResolvedSiteBlockDTO) {
                $resolved[] = [
                    'title' => $block->title,
                    // Names the section for assistive technology when it has no title
                    'label' => $this->labelOf($block->key),
                    // Before or after the calendar (link-page#53); the page keeps the order inside each
                    'position' => $block->position->value,
                    'block' => $drawn->toArray(),
                ];
            }
        }

        return $resolved;
    }

    private function labelOf(string $key): ?string
    {
        try {
            $label = $this->registry->find($key)?->label();
        } catch (Throwable) {
            return null;
        }

        return is_string($label) && trim($label) !== '' ? trim($label) : null;
    }
}
