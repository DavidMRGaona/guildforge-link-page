<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

use Modules\LinkPage\Domain\Enums\ContrastPair;

/**
 * A colour pair with the worst ratio found (a gradient or an image puts the text on more than
 * one colour) and the minimum it needs; a failure when passes() is false.
 */
final readonly class ContrastCheck
{
    public float $minimum;

    public function __construct(
        public ContrastPair $pair,
        public float $ratio,
    ) {
        $this->minimum = $pair->minimum();
    }

    public function passes(): bool
    {
        return $this->ratio >= $this->minimum;
    }
}
