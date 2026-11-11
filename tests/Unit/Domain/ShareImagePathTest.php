<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use Modules\LinkPage\Domain\ValueObjects\ShareImagePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShareImagePathTest extends TestCase
{
    #[DataProvider('usablePaths')]
    public function test_it_accepts_png_and_jpg_files_of_the_images_disk(string $path): void
    {
        $this->assertTrue(ShareImagePath::isUsable($path));
    }

    /**
     * The card's own uploads and files chosen from the library (the picker checked they exist).
     *
     * @return array<string, array{string}>
     */
    public static function usablePaths(): array
    {
        return [
            'a month folder' => ['link-page/share/2026/11/5f0c1a3e-1111-4222-8333-444455556666.jpg'],
            'a png' => ['link-page/share/2026/11/card.png'],
            'a jpeg' => ['link-page/share/card.JPEG'],
            'the appearance images' => ['link-page/2026/11/logo.png'],
            'the site logo' => ['settings/logo.png'],
            'an event image' => ['events/2026/11/poster.jpg'],
            'a file at the root' => ['poster.jpg'],
        ];
    }

    #[DataProvider('unusablePaths')]
    public function test_it_rejects_anything_else(string $path): void
    {
        $this->assertFalse(ShareImagePath::isUsable($path));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusablePaths(): array
    {
        return [
            'climbing out' => ['link-page/share/../qr/logo.png'],
            'a dotted name' => ['link-page/share/2026/card.v2.png'],
            'webp, which the card does not take' => ['link-page/share/2026/card.webp'],
            'an svg' => ['settings/logo.svg'],
            'a url' => ['https://example.com/link-page/share/card.png'],
            'an absolute path' => ['/events/2026/11/poster.jpg'],
            'a space' => ['events/2026/11/my poster.jpg'],
            'a quote' => ['events/2026/11/a").jpg'],
            'an empty folder' => ['events//poster.jpg'],
            'a trailing line break' => ["link-page/share/card.png\n"],
            'empty' => [''],
        ];
    }
}
