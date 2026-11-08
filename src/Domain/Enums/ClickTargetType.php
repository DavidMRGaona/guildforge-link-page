<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * What a counted click on the page was: the prefix of its stable id (`data-link-id`).
 */
enum ClickTargetType: string
{
    use HasTranslatedOptions;

    case Link = 'link';
    case Social = 'social';
    case Event = 'event';
    case Calendar = 'calendar';
    case Home = 'home';

    protected static function translationGroup(): string
    {
        return 'stats.target_types';
    }
}
