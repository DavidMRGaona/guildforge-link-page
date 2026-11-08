<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;
use Modules\LinkPage\Domain\ValueObjects\LinkUrl;

/**
 * The social networks and contact channels of the icon row. The site keeps their addresses
 * once, in its own settings (Sobre nosotros → Redes sociales and Contacto), and the page only
 * reads them: nothing is typed twice. The page holds at most one icon per network, so the
 * network is also the stable id of its link.
 */
enum SocialNetwork: string
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

    public function icon(): LinkIcon
    {
        return LinkIcon::from($this->value);
    }

    /**
     * The key of the site settings that holds this network's address: `social_<network>` for
     * the profiles (the site's own name for X is "twitter"), and the contact details for the
     * mail and the phone.
     */
    public function siteSettingKey(): string
    {
        return match ($this) {
            self::X => 'social_twitter',
            self::Email => 'contact_email',
            self::Phone => 'contact_phone',
            default => 'social_'.$this->value,
        };
    }

    /** Mail and phone take their own scheme; every network profile is a web address. */
    public function accepts(LinkUrl $url): bool
    {
        return match ($this) {
            self::Email => $url->scheme() === 'mailto',
            self::Phone => $url->scheme() === 'tel',
            default => $url->scheme() === 'https',
        };
    }

    protected static function translationGroup(): string
    {
        return 'social_networks';
    }
}
