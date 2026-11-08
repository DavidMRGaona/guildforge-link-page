<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Support;

use Illuminate\Support\Facades\Storage;
use Modules\LinkPage\Domain\ValueObjects\ShareImagePath;

/**
 * Between the share section of the settings form and the stored `share` settings.
 */
final class ShareFormState
{
    /**
     * What gets stored: the texts trimmed (empty ones as null, so the card falls back to the
     * site's), the option as a real boolean and the image only when it is a file this form
     * stores, whatever a forged form state sent.
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
            'image' => is_string($image) && ShareImagePath::isOwn($image) ? $image : null,
            'noindex' => ($state['noindex'] ?? false) === true,
        ];
    }

    /**
     * Deletes the stored image once it is replaced or removed, so no file is left on the images
     * disk. Only the form's own files are ever deleted.
     */
    public static function deleteReplacedImage(?string $before, ?string $after): void
    {
        if ($before !== null && $before !== $after && ShareImagePath::isOwn($before)) {
            Storage::disk('images')->delete($before);
        }
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
