<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

use LogicException;
use Modules\LinkPage\Domain\Enums\ClickTargetType;
use Modules\LinkPage\Domain\Enums\SocialNetwork;

/**
 * Something on the page whose clicks are counted, by the id that never changes:
 * `link:<uuid>` (own link), `social:<network>`, `event:<uuid>` (event button),
 * `featured:<uuid>` (the featured event), `home` (the link to the site), `notice` (the link of
 * the notice) and `calendar:<source>:<id>` (an entry opened from the calendar). It names what
 * was clicked, never who clicked it.
 */
final readonly class ClickTarget
{
    public const int MAX_LENGTH = 120;

    private const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /** Calendar sources and their ids: plain slugs and keys, nothing markup could hide in. */
    private const string CALENDAR_PART = '[a-z0-9][a-z0-9._-]{0,47}';

    private function __construct(
        public ClickTargetType $type,
        public string $reference,
    ) {}

    public static function tryFrom(string $value): ?self
    {
        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        if ($value === ClickTargetType::Home->value || $value === ClickTargetType::Notice->value) {
            return new self(ClickTargetType::from($value), '');
        }

        if (preg_match('/^(link|event|featured):('.self::UUID.')\z/', strtolower($value), $matches) === 1) {
            return new self(ClickTargetType::from($matches[1]), $matches[2]);
        }

        if (preg_match('/^social:([a-z]+)\z/', $value, $matches) === 1 && SocialNetwork::tryFrom($matches[1]) !== null) {
            return new self(ClickTargetType::Social, $matches[1]);
        }

        if (preg_match('/^calendar:('.self::CALENDAR_PART.'):('.self::CALENDAR_PART.')\z/i', $value, $matches) === 1) {
            return new self(ClickTargetType::Calendar, $matches[1].':'.$matches[2]);
        }

        return null;
    }

    /**
     * An entry of the public calendar, or null when its source or id cannot be part of an id.
     */
    public static function tryCalendar(string $sourceType, string $id): ?self
    {
        return self::tryFrom('calendar:'.$sourceType.':'.$id);
    }

    /**
     * @throws LogicException when the source or id cannot be part of an id
     */
    public static function calendar(string $sourceType, string $id): self
    {
        return self::tryCalendar($sourceType, $id) ?? throw new LogicException('The calendar entry has no usable id.');
    }

    public static function home(): self
    {
        return new self(ClickTargetType::Home, '');
    }

    /** What the statistics store: the same id the page marks the element with. */
    public function value(): string
    {
        return $this->reference === ''
            ? $this->type->value
            : $this->type->value.':'.$this->reference;
    }

    public function network(): ?SocialNetwork
    {
        return $this->type === ClickTargetType::Social ? SocialNetwork::tryFrom($this->reference) : null;
    }

    /**
     * @return array{string, string}|null the source type and the entry id
     */
    public function calendarEntry(): ?array
    {
        if ($this->type !== ClickTargetType::Calendar) {
            return null;
        }

        [$source, $id] = explode(':', $this->reference, 2);

        return [$source, $id];
    }
}
