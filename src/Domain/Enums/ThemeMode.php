<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * Which colour set the page uses. Device follows the class the core puts on the document
 * before the first paint: the visitor's choice on the site, else the site default, else the
 * system's.
 */
enum ThemeMode: string
{
    use HasTranslatedOptions;

    case Device = 'device';
    case Light = 'light';
    case Dark = 'dark';

    /**
     * @return non-empty-list<'light'|'dark'> the colour sets this mode can show
     */
    public function schemesInUse(): array
    {
        return match ($this) {
            self::Device => ['light', 'dark'],
            self::Light => ['light'],
            self::Dark => ['dark'],
        };
    }

    protected static function translationGroup(): string
    {
        return 'theme_modes';
    }
}
