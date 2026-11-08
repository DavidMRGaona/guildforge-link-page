<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * Whether a link shows on the page right now, and if not, why.
 */
enum LinkVisibility: string
{
    use HasTranslatedOptions;

    /** Switched on and inside its dates, if it has any. */
    case Visible = 'visible';

    /** Switched on, but its start date has not come yet. */
    case Scheduled = 'scheduled';

    /** Switched on, but its end date has passed. */
    case Expired = 'expired';

    /** Switched off, whatever its dates. */
    case Hidden = 'hidden';

    public function color(): string
    {
        return match ($this) {
            self::Visible => 'success',
            self::Scheduled => 'info',
            self::Expired => 'warning',
            self::Hidden => 'gray',
        };
    }

    protected static function translationGroup(): string
    {
        return 'link_visibility';
    }
}
