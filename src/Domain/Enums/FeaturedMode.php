<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * Which event the featured block shows: none, the next one (optionally with a tag) or a
 * chosen one.
 */
enum FeaturedMode: string
{
    use HasTranslatedOptions;

    case Off = 'off';
    case Auto = 'auto';
    case Manual = 'manual';

    protected static function translationGroup(): string
    {
        return 'featured.modes';
    }
}
