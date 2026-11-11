<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Support;

use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;

/**
 * The images the link page settings hold now. The settings page has no record, so its image
 * fields accept these paths as stored ones (moving an image from one field to another
 * included), on top of the files of the media library each field takes.
 */
final class StoredSettingsImages
{
    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        $settings = app(LinkPageSettingsInterface::class);
        $appearance = $settings->appearance();

        return array_values(array_unique(array_filter(
            [$appearance->logoImage, $appearance->backgroundImage, $settings->shareImage()],
            static fn (?string $path): bool => $path !== null,
        )));
    }
}
