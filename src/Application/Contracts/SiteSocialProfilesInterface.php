<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Contracts;

use Modules\LinkPage\Domain\Enums\SocialNetwork;
use Modules\LinkPage\Domain\ValueObjects\LinkUrl;

/**
 * The social profiles the site keeps once for everybody (Sobre nosotros → Redes sociales).
 * The page reads them from there instead of asking for them again.
 */
interface SiteSocialProfilesInterface
{
    /**
     * Each network the site has a usable address for, in catalogue order. A saved value that is
     * not a safe https address is left out.
     *
     * @return array<string, LinkUrl> keyed by the network's value
     */
    public function all(): array;

    public function for(SocialNetwork $network): ?LinkUrl;
}
