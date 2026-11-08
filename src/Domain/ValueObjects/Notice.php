<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Modules\LinkPage\Domain\Enums\NoticeTone;

/**
 * The temporary notice at the top of the page: a short text, an optional link, a tone and the
 * window in which it shows. The window is made of instants, so it does not depend on any zone;
 * `[startsAt, endsAt)`, either end optional.
 */
final readonly class Notice
{
    public const int MAX_TEXT_LENGTH = 160;

    public string $text;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $text,
        public NoticeTone $tone,
        public ?NoticeLink $link = null,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $endsAt = null,
    ) {
        $text = trim($text);

        if ($text === '' || mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new InvalidArgumentException('A notice text has between 1 and '.self::MAX_TEXT_LENGTH.' characters.');
        }

        if ($startsAt instanceof DateTimeImmutable && $endsAt instanceof DateTimeImmutable && $endsAt <= $startsAt) {
            throw new InvalidArgumentException('A notice must end after it starts.');
        }

        $this->text = $text;
    }

    public function isVisibleAt(DateTimeInterface $instant): bool
    {
        return (! $this->startsAt instanceof DateTimeImmutable || $this->startsAt <= $instant)
            && (! $this->endsAt instanceof DateTimeImmutable || $instant < $this->endsAt);
    }

    /**
     * @param  array<array-key, mixed>  $stored
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $stored): self
    {
        $text = $stored['text'] ?? null;
        $tone = $stored['tone'] ?? null;
        $url = $stored['link_url'] ?? null;
        $label = $stored['link_label'] ?? null;

        if (! is_string($text)) {
            throw new InvalidArgumentException('A notice needs a text.');
        }

        $tone = is_string($tone) ? NoticeTone::tryFrom($tone) : null;
        if ($tone === null) {
            throw new InvalidArgumentException('Unknown notice tone.');
        }

        return new self(
            $text,
            $tone,
            is_string($url) && trim($url) !== '' ? new NoticeLink(trim($url), is_string($label) ? $label : null) : null,
            self::instant($stored['starts_at'] ?? null),
            self::instant($stored['ends_at'] ?? null),
        );
    }

    /**
     * The stored shape (without the on/off switch, which the settings keep).
     *
     * @return array{text: string, tone: string, link_url: string|null, link_label: string|null, starts_at: string|null, ends_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'tone' => $this->tone->value,
            'link_url' => $this->link?->url,
            'link_label' => $this->link?->label,
            'starts_at' => $this->startsAt?->format(DateTimeInterface::ATOM),
            'ends_at' => $this->endsAt?->format(DateTimeInterface::ATOM),
        ];
    }

    private static function instant(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('A notice date is an ISO 8601 string.');
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw new InvalidArgumentException('A notice date is an ISO 8601 string.');
        }
    }
}
