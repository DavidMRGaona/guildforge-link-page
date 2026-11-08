<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * The frame of the picture at the top of the page.
 */
enum LogoShape: string
{
    use HasTranslatedOptions;

    case Circle = 'circle';
    case Rounded = 'rounded';

    protected static function translationGroup(): string
    {
        return 'logo_shapes';
    }
}
