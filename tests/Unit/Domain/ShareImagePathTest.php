<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use Modules\LinkPage\Domain\ValueObjects\ShareImagePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShareImagePathTest extends TestCase
{
    #[DataProvider('ownPaths')]
    public function test_it_accepts_the_files_the_share_form_stores(string $path): void
    {
        $this->assertTrue(ShareImagePath::isOwn($path));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ownPaths(): array
    {
        return [
            'a month folder' => ['link-page/share/2026/11/5f0c1a3e-1111-4222-8333-444455556666.jpg'],
            'a png' => ['link-page/share/2026/11/card.png'],
            'a jpeg' => ['link-page/share/card.JPEG'],
        ];
    }

    #[DataProvider('foreignPaths')]
    public function test_it_rejects_any_other_file(string $path): void
    {
        $this->assertFalse(ShareImagePath::isOwn($path));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function foreignPaths(): array
    {
        return [
            'the appearance images' => ['link-page/2026/11/logo.png'],
            'the qr logos' => ['link-page/qr/2026/11/logo.png'],
            'the site logo' => ['settings/logo.png'],
            'an event image' => ['events/2026/11/poster.jpg'],
            'climbing out' => ['link-page/share/../qr/logo.png'],
            'a dotted name' => ['link-page/share/2026/card.v2.png'],
            'webp, which the card does not take' => ['link-page/share/2026/card.webp'],
            'a url' => ['https://example.com/link-page/share/card.png'],
            'a prefix trick' => ['xlink-page/share/card.png'],
            'a trailing line break' => ["link-page/share/card.png\n"],
            'empty' => [''],
        ];
    }
}
