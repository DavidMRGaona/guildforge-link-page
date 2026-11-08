<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * What fills the page behind its content.
 */
enum BackgroundType: string
{
    use HasTranslatedOptions;

    case Solid = 'solid';
    case Gradient = 'gradient';
    case Image = 'image';

    protected static function translationGroup(): string
    {
        return 'background_types';
    }
}
