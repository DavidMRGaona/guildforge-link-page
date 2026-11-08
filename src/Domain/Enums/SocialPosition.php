<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * Where the row of social icons goes: under the site name or at the end of the page.
 */
enum SocialPosition: string
{
    use HasTranslatedOptions;

    case Top = 'top';
    case Bottom = 'bottom';

    protected static function translationGroup(): string
    {
        return 'social_positions';
    }
}
