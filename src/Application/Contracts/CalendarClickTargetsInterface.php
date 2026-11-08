<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Contracts;

use Modules\LinkPage\Domain\ValueObjects\ClickTarget;

/**
 * Entries of the page's calendar by the address they open. The calendar is the core's and its
 * entries carry no id of the page, so a click on one is known by where it leads.
 */
interface CalendarClickTargetsInterface
{
    /**
     * The entry of the public calendar (a few months back and a year ahead) that opens this
     * address of the site, or null for any other address.
     */
    public function forUrl(string $url): ?ClickTarget;
}
