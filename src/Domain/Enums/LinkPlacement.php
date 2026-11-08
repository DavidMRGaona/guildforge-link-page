<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * Whether an own link goes above or below the event buttons; inside each group the links keep
 * the order they were dragged into.
 */
enum LinkPlacement: string
{
    use HasTranslatedOptions;

    case Before = 'before';
    case After = 'after';

    protected static function translationGroup(): string
    {
        return 'link_placements';
    }
}
