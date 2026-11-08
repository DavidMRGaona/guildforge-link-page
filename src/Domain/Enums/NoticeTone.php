<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * How loud a notice is. The tones differ by icon, shape and the word that names them, never by
 * a colour of their own: the page paints them with pairs of colours it already validated.
 */
enum NoticeTone: string
{
    use HasTranslatedOptions;

    case Info = 'info';
    case Warning = 'warning';
    case Important = 'important';

    protected static function translationGroup(): string
    {
        return 'notice.tones';
    }
}
