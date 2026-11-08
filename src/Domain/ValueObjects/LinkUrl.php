<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * An address a button on the page may lead to: https, mailto or tel, nothing else.
 *
 * The scheme is checked on the whole text, case-insensitively and after trimming, and anything
 * that could hide another scheme (whitespace or control characters inside, a no-break space, a
 * zero-width character) is refused, so `JavaScript:`, `java\tscript:` or `data:` never get through.
 * Phone numbers are stored without punctuation, so the href is a valid `tel:` URI.
 */
final readonly class LinkUrl implements Stringable
{
    public const int MAX_LENGTH = 2048;

    private const string HTTPS = 'https';

    private const string MAILTO = 'mailto';

    private const string TEL = 'tel';

    /** Whitespace, control and format characters (zero-width, bidirectional overrides…) and what no address needs. */
    private const string FORBIDDEN = '/[\s\p{C}\p{Z}<>"\\\\]/u';

    private const string HOST = '/^(?:[\p{L}\p{N}](?:[\p{L}\p{N}\-]*[\p{L}\p{N}])?)(?:\.[\p{L}\p{N}](?:[\p{L}\p{N}\-]*[\p{L}\p{N}])?)*$/u';

    private const string IPV6_HOST = '/^\[[0-9A-Fa-f:.]{2,45}\]$/';

    private const string MAILTO_QUERY = '/^[\w%.~!$&\'()*+,;=:@\/?\-]*$/';

    private const string PHONE = '/^\+?[0-9]{3,20}$/';

    private function __construct(public string $value) {}

    /**
     * @throws InvalidArgumentException when it is not a well-formed https, mailto or tel address
     */
    public static function from(string $value): self
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            throw new InvalidArgumentException('A link must be a well-formed https, mailto or tel address.');
        }

        return new self($normalized);
    }

    public static function tryFrom(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $normalized = self::normalize($value);

        return $normalized === null ? null : new self($normalized);
    }

    /** Whether the page leaves for another site (https); mail and phone links open an app instead. */
    public function isExternal(): bool
    {
        return $this->scheme() === self::HTTPS;
    }

    public function scheme(): string
    {
        return strstr($this->value, ':', true) ?: '';
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function normalize(string $value): ?string
    {
        // Not trim()'s default list: it would swallow a trailing NUL instead of refusing it
        $value = trim($value, " \t\n\r\x0B\x0C");

        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):(.*)$/su', $value, $parts) !== 1) {
            return null;
        }

        $scheme = strtolower($parts[1]);
        $rest = $parts[2];

        // People type phone numbers with spaces ("+34 600 123 456"); they go, and nothing else
        // that is blank or invisible may stay inside any address
        if ($scheme === self::TEL) {
            $rest = str_replace(' ', '', $rest);
        }

        if (preg_match(self::FORBIDDEN, $rest) !== 0) {
            return null;
        }

        return match ($scheme) {
            self::HTTPS => self::https($rest),
            self::MAILTO => self::mailto($rest),
            self::TEL => self::tel($rest),
            default => null,
        };
    }

    private static function https(string $rest): ?string
    {
        if (! str_starts_with($rest, '//')) {
            return null;
        }

        $parts = parse_url(self::HTTPS.':'.$rest);

        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? '';

        if (isset($parts['user']) || isset($parts['pass']) || $host === '') {
            return null;
        }

        if (preg_match(self::HOST, $host) !== 1 && preg_match(self::IPV6_HOST, $host) !== 1) {
            return null;
        }

        return self::HTTPS.':'.$rest;
    }

    private static function mailto(string $rest): ?string
    {
        $address = strstr($rest, '?', true);
        $query = $address === false ? '' : substr($rest, strlen($address) + 1);
        $address = $address === false ? $rest : $address;

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false || preg_match(self::MAILTO_QUERY, $query) !== 1) {
            return null;
        }

        return self::MAILTO.':'.$rest;
    }

    private static function tel(string $rest): ?string
    {
        // Spaces cannot reach here: the whitespace check ran on the whole text
        $number = str_replace(['(', ')', '-', '.'], '', $rest);

        return preg_match(self::PHONE, $number) === 1 ? self::TEL.':'.$number : null;
    }
}
