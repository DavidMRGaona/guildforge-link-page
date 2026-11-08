<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Contracts;

use Modules\LinkPage\Application\DTOs\LinkPageLinkDTO;

/**
 * The own links the page shows right now.
 */
interface LinkPageLinksInterface
{
    /**
     * Switched on and inside their dates at this instant, in the order they were dragged into.
     * A link whose stored address is not a valid https, mailto or tel one is left out.
     *
     * @return list<LinkPageLinkDTO>
     */
    public function visible(): array;
}
