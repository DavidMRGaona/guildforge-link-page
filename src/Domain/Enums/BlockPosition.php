<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * Where a site block goes on the link page (link-page#53): before the calendar, after the links that
 * follow the events (where blocks always went), or after the calendar and its subscribe button, before
 * the social row when it is at the bottom. Inside each group the blocks keep their order.
 */
enum BlockPosition: string
{
    use HasTranslatedOptions;

    case BeforeCalendar = 'before_calendar';
    case AfterCalendar = 'after_calendar';

    /**
     * A stored position, read tolerantly: anything unknown is before the calendar.
     */
    public static function fromStored(mixed $position): self
    {
        return (is_string($position) ? self::tryFrom($position) : null) ?? self::BeforeCalendar;
    }

    protected static function translationGroup(): string
    {
        return 'block_positions';
    }
}
