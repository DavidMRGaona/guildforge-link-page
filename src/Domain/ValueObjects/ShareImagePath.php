<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

/**
 * The image of the share card on the images disk: the share form's own uploads (under
 * link-page/share) or a file chosen from the media library, which the form checked. Only plain
 * relative PNG or JPG paths are used, whatever ended up in the settings.
 */
final class ShareImagePath
{
    public const string DIRECTORY = 'link-page/share';

    /** JPG or PNG: the formats the form takes, which every app that builds a preview shows. */
    private const string PATTERN = '#^(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.(?:png|jpe?g)$#Di';

    public static function isUsable(string $path): bool
    {
        return preg_match(self::PATTERN, $path) === 1;
    }
}
