<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use Modules\LinkPage\Domain\ValueObjects\QrLogoPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QrLogoPathTest extends TestCase
{
    #[DataProvider('ownPaths')]
    public function test_it_accepts_the_files_the_qr_form_stores(string $path): void
    {
        $this->assertTrue(QrLogoPath::isOwn($path));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ownPaths(): array
    {
        return [
            'a month folder' => ['link-page/qr/2026/11/5f0c1a3e-1111-4222-8333-444455556666.png'],
            'a jpeg' => ['link-page/qr/2026/11/logo.JPEG'],
            'a webp' => ['link-page/qr/logo.webp'],
            'an svg' => ['link-page/qr/2026/11/logo.svg'],
        ];
    }

    #[DataProvider('foreignPaths')]
    public function test_it_rejects_any_other_file(string $path): void
    {
        $this->assertFalse(QrLogoPath::isOwn($path));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function foreignPaths(): array
    {
        return [
            'the appearance images' => ['link-page/2026/11/logo.svg'],
            'the share cards' => ['link-page/share/2026/11/card.png'],
            'the site logo' => ['branding/logo.svg'],
            'climbing out' => ['link-page/qr/../share/card.png'],
            'a dotted name' => ['link-page/qr/2026/logo.v2.svg'],
            'a gif' => ['link-page/qr/2026/logo.gif'],
            'an svgz' => ['link-page/qr/2026/logo.svgz'],
            'a url' => ['https://example.com/link-page/qr/logo.svg'],
            'a trailing line break' => ["link-page/qr/logo.svg\n"],
            'empty' => [''],
        ];
    }
}
