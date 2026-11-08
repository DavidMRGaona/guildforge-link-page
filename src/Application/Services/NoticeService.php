<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Services;

use Modules\LinkPage\Application\Contracts\ClockInterface;
use Modules\LinkPage\Application\Contracts\HighlightsSettingsInterface;
use Modules\LinkPage\Domain\ValueObjects\Notice;

/**
 * The notice the page shows right now: the configured one while its window is open.
 */
final readonly class NoticeService
{
    public function __construct(
        private HighlightsSettingsInterface $settings,
        private ClockInterface $clock,
    ) {}

    public function current(): ?Notice
    {
        $notice = $this->settings->notice();

        return $notice instanceof Notice && $notice->isVisibleAt($this->clock->now()) ? $notice : null;
    }
}
