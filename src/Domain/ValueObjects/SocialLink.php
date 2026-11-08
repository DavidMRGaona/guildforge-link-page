<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\LinkPage\Domain\Enums\SocialNetwork;

/**
 * One icon of the social row: a network and an address that fits it.
 */
final readonly class SocialLink
{
    /**
     * @throws InvalidArgumentException when the address does not fit the network
     */
    public function __construct(
        public SocialNetwork $network,
        public LinkUrl $url,
    ) {
        if (! $network->accepts($url)) {
            throw new InvalidArgumentException("The address does not fit the {$network->value} network.");
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     *
     * @throws InvalidArgumentException when the network is unknown or the address is not valid for it
     */
    public static function fromArray(array $values): self
    {
        $network = $values['network'] ?? null;
        $url = $values['url'] ?? null;

        if (! is_string($network) || ! is_string($url)) {
            throw new InvalidArgumentException('A social link needs a network and an address, both text.');
        }

        $case = SocialNetwork::tryFrom($network);

        if ($case === null) {
            throw new InvalidArgumentException("Unknown social network \"{$network}\".");
        }

        return new self($case, LinkUrl::from($url));
    }

    /**
     * What the statistics count and the page marks the link with; it never changes.
     */
    public function linkId(): string
    {
        return 'social:'.$this->network->value;
    }

    /**
     * @return array{network: string, url: string}
     */
    public function toArray(): array
    {
        return ['network' => $this->network->value, 'url' => $this->url->value];
    }
}
