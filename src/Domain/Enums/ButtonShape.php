<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * The corners of the link buttons.
 */
enum ButtonShape: string
{
    use HasTranslatedOptions;

    case Square = 'square';
    case Rounded = 'rounded';
    case Pill = 'pill';

    protected static function translationGroup(): string
    {
        return 'button_shapes';
    }
}
