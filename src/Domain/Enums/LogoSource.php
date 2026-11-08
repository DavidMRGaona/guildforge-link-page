<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * The picture at the top of the page: the site logo, an image of its own or the initial of the
 * site name.
 */
enum LogoSource: string
{
    use HasTranslatedOptions;

    case Site = 'site';
    case Custom = 'custom';
    case Initial = 'initial';

    protected static function translationGroup(): string
    {
        return 'logo_sources';
    }
}
