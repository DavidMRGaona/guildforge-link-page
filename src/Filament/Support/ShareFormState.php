<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Support;

use Modules\LinkPage\Domain\ValueObjects\ShareImagePath;

/**
 * Between the share section of the settings form and the stored `share` settings.
 */
final class ShareFormState
{
    /**
     * What gets stored: the texts trimmed (empty ones as null, so the card falls back to the
     * site's), the option as a real boolean and the image only when the card can use it (the
     * field's picker already refused any file that is neither stored nor in the library).
     *
     * @param  array<array-key, mixed>  $state
     * @return array{title: string|null, description: string|null, image: string|null, noindex: bool}
     */
    public static function toStored(array $state): array
    {
        $image = $state['image'] ?? null;

        return [
            'title' => self::text($state['title'] ?? null),
            'description' => self::text($state['description'] ?? null),
            'image' => is_string($image) && ShareImagePath::isUsable($image) ? $image : null,
            'noindex' => ($state['noindex'] ?? false) === true,
        ];
    }

    /**
     * Whether the form holds an image the card cannot use (a file chosen from the library whose
     * name has spaces or other characters), so the field can say so instead of dropping it.
     *
     * @param  array<array-key, mixed>  $state
     */
    public static function hasUnusableImage(array $state): bool
    {
        $image = $state['image'] ?? null;

        return is_string($image) && $image !== '' && ! ShareImagePath::isUsable($image);
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
