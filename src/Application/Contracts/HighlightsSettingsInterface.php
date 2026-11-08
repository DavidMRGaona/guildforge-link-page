<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Contracts;

use Modules\LinkPage\Domain\ValueObjects\FeaturedSettings;
use Modules\LinkPage\Domain\ValueObjects\Notice;

/**
 * The temporary notice and the featured event, read defensively like the rest of the page
 * settings: a malformed stored value reads as "nothing to show".
 */
interface HighlightsSettingsInterface
{
    /** The notice when it is switched on and valid, whatever its dates; null otherwise. */
    public function notice(): ?Notice;

    /**
     * The notice as the panel form shows it, switched on or off and even half-written, with
     * the dates as stored (ISO 8601).
     *
     * @return array{enabled: bool, text: string|null, tone: string, link_url: string|null, link_label: string|null, starts_at: string|null, ends_at: string|null}
     */
    public function noticeForForm(): array;

    public function featured(): FeaturedSettings;
}
