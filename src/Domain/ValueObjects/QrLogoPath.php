<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

/**
 * Where uploaded QR logos live on the images disk. A logo path is only read, kept or deleted
 * when it is one of these files: a forged form state cannot point a QR code at another file
 * (the site logo, an event image) nor get that file deleted.
 */
final class QrLogoPath
{
    public const string DIRECTORY = 'link-page/qr';

    /** Sub-directories and a file name of letters, digits, "-" and "_": no dots to climb out. */
    private const string PATTERN = '#^link-page/qr/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.(?:png|jpe?g|webp|svg)$#Di';

    public static function isOwn(string $path): bool
    {
        return preg_match(self::PATTERN, $path) === 1;
    }
}
