<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * How the link buttons are drawn. An outline button shows its text in the button colour on
 * the page background, so that colour has to read as text there.
 */
enum ButtonStyle: string
{
    use HasTranslatedOptions;

    case Filled = 'filled';
    case Outline = 'outline';
    case Shadow = 'shadow';

    protected static function translationGroup(): string
    {
        return 'button_styles';
    }
}
