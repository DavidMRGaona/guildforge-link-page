<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use App\Application\Services\AboutPageServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use Modules\LinkPage\Application\Contracts\SiteSocialProfilesInterface;
use Modules\LinkPage\Domain\Enums\SocialNetwork;
use Modules\LinkPage\Domain\ValueObjects\LinkUrl;

/**
 * Reads the site's settings (`social_<network>`, `contact_email`, `contact_phone`) and gives
 * the profiles the same address format the site's own pages use (https:// when it was saved
 * without a scheme). A network the core does not know yet simply has no address.
 */
final readonly class CoreSiteSocialProfiles implements SiteSocialProfilesInterface
{
    public function __construct(
        private SettingsServiceInterface $settings,
        private AboutPageServiceInterface $aboutPage,
    ) {}

    public function all(): array
    {
        $profiles = [];

        foreach (SocialNetwork::cases() as $network) {
            $url = $this->for($network);

            if ($url instanceof LinkUrl) {
                $profiles[$network->value] = $url;
            }
        }

        return $profiles;
    }

    public function for(SocialNetwork $network): ?LinkUrl
    {
        $value = $this->settings->get($network->siteSettingKey(), '');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $address = match ($network) {
            SocialNetwork::Email => 'mailto:'.trim($value),
            SocialNetwork::Phone => 'tel:'.trim($value),
            default => $this->aboutPage->formatSocialUrl(trim($value)),
        };

        $url = LinkUrl::tryFrom($address);

        // Only an address of the kind the network takes goes on the page, whatever the site saved
        return $url instanceof LinkUrl && $network->accepts($url) ? $url : null;
    }
}
