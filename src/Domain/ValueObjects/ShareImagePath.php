<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

/**
 * Where the image of the share card lives on the images disk. A path is only used on the card,
 * kept or deleted when it is one of these files: a forged form state cannot point the card at
 * another file (the site logo, an event image) nor get that file deleted.
 */
final class ShareImagePath
{
    public const string DIRECTORY = 'link-page/share';

    /** JPG or PNG: the formats the form takes, which every app that builds a preview shows. */
    private const string PATTERN = '#^link-page/share/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.(?:png|jpe?g)$#Di';

    public static function isOwn(string $path): bool
    {
        return preg_match(self::PATTERN, $path) === 1;
    }
}
