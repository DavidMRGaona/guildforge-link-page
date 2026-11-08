<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\DTOs;

use Modules\LinkPage\Domain\Enums\LinkIcon;
use Modules\LinkPage\Domain\Enums\LinkPlacement;
use Modules\LinkPage\Domain\ValueObjects\LinkUrl;

/**
 * An own link as the page draws it.
 */
final readonly class LinkPageLinkDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public LinkUrl $url,
        public ?string $description,
        public ?LinkIcon $icon,
        public bool $featured,
        public LinkPlacement $placement,
    ) {}

    /**
     * What the page marks the button with and the statistics count it by; it never changes.
     */
    public function linkId(): string
    {
        return 'link:'.$this->id;
    }
}
