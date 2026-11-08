<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * The pictograms a link may carry, from a closed list: the front end draws each one as an
 * inline SVG shipped with the module (resources/js/utils/linkIcons.ts), so nothing is ever
 * loaded from another site and no stored text becomes markup.
 *
 * The first twelve are the social networks and contact channels (see SocialNetwork); the
 * rest, starting with the website globe, are generic.
 */
enum LinkIcon: string
{
    use HasTranslatedOptions;

    case Instagram = 'instagram';
    case Discord = 'discord';
    case Telegram = 'telegram';
    case Whatsapp = 'whatsapp';
    case Youtube = 'youtube';
    case Twitch = 'twitch';
    case X = 'x';
    case Facebook = 'facebook';
    case Tiktok = 'tiktok';
    case Bluesky = 'bluesky';
    case Email = 'email';
    case Phone = 'phone';
    case Website = 'website';
    case Link = 'link';
    case Calendar = 'calendar';
    case Group = 'group';
    case Book = 'book';
    case Dice = 'dice';
    case Ticket = 'ticket';
    case MapPin = 'map_pin';
    case Form = 'form';

    protected static function translationGroup(): string
    {
        return 'link_icons';
    }
}
