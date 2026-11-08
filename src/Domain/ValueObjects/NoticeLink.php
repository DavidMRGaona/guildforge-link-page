<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The link of a notice: an https address or a path of this site, never another scheme (a
 * stored text must not become a javascript: or data: link).
 */
final readonly class NoticeLink
{
    public const int MAX_URL_LENGTH = 500;

    public const int MAX_LABEL_LENGTH = 40;

    public ?string $label;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(public string $url, ?string $label)
    {
        if (! self::isValidUrl($url)) {
            throw new InvalidArgumentException('A notice link is an https address or a path of this site.');
        }

        $label = $label === null ? null : trim($label);

        if ($label !== null && mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            throw new InvalidArgumentException('The label of a notice link is too long.');
        }

        $this->label = $label === '' ? null : $label;
    }

    public static function isValidUrl(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return false;
        }

        // A path of this site: one slash, not two (that would be another host) and no backslash
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        if (! str_starts_with($url, 'https://')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '';
    }
}
