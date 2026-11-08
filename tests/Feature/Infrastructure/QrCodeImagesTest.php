<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Infrastructure;

use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\Services\SettingsServiceInterface;
use Illuminate\Support\Facades\Storage;
use Modules\LinkPage\Application\Contracts\QrCodeImagesInterface;
use Modules\LinkPage\Domain\Enums\QrLogoSource;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\QrCodes\QrCodeTestImages;

/**
 * The images people print: they must always decode to the stable /qr/{code} address.
 */
final class QrCodeImagesTest extends ModuleTestCase
{
    use CreatesQrCodes;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
    }

    /**
     * @return array<string, array{int}>
     */
    public static function pngSizes(): array
    {
        return array_combine(
            array_map(fn (int $size): string => $size.' px', QrCodeImagesInterface::PNG_SIZES),
            array_map(fn (int $size): array => [$size], QrCodeImagesInterface::PNG_SIZES),
        );
    }

    #[DataProvider('pngSizes')]
    public function test_each_png_size_decodes_to_the_stable_address(int $size): void
    {
        $qr = $this->qrCode();

        $png = $this->images()->render($qr->toDesign(), $size, QrCodeFormat::Png);

        $this->assertSame([$size, $size], array_slice((array) getimagesizefromstring($png->contents), 0, 2));
        $this->assertSame(route('link_page.qr', $qr->code), QrCodeTestImages::decode($png->contents));
        $this->assertStringEndsWith('/qr/'.$qr->code, QrCodeTestImages::decode($png->contents));
    }

    public function test_the_svg_is_a_drawing_of_the_same_code(): void
    {
        $svg = $this->images()->render($this->qrCode()->toDesign(), 1024, QrCodeFormat::Svg);

        $this->assertStringContainsString('<svg', $svg->contents);
        $this->assertSame('image/svg+xml', $svg->mimeType());
    }

    public function test_it_uses_the_colours_and_the_margin_of_the_qr_code(): void
    {
        $qr = $this->qrCode(['foreground_color' => '#1F2937', 'background_color' => '#FFF8E7', 'margin' => 10]);

        $png = $this->images()->render($qr->toDesign(), 512, QrCodeFormat::Png)->contents;

        // The margin (10 % of 512 px, about 51 px) is all background
        $this->assertSame([0xFF, 0xF8, 0xE7], QrCodeTestImages::pixel($png, 45, 45));
        $this->assertSame(route('link_page.qr', $qr->code), QrCodeTestImages::decode($png));
    }

    public function test_the_site_logo_goes_in_the_centre_and_the_code_still_decodes(): void
    {
        Storage::disk('images')->put('branding/logo.png', QrCodeTestImages::png(200, 200));
        app(SettingsServiceInterface::class)->set('site_logo_light', 'branding/logo.png');
        $withLogo = $this->qrCode(['logo_source' => QrLogoSource::Site]);

        $png = $this->images()->render($withLogo->toDesign(), 1024, QrCodeFormat::Png)->contents;
        $plainPng = $this->images()->render($withLogo->toDesign()->withoutLogo(), 1024, QrCodeFormat::Png)->contents;

        $this->assertNotSame($plainPng, $png);
        $this->assertSame(route('link_page.qr', $withLogo->code), QrCodeTestImages::decode($png));
    }

    public function test_an_uploaded_logo_goes_in_the_centre(): void
    {
        Storage::disk('images')->put('link-page/qr/own.png', QrCodeTestImages::png(120, 80));
        $qr = $this->qrCode(['logo_source' => QrLogoSource::Custom, 'logo_path' => 'link-page/qr/own.png']);

        $png = $this->images()->render($qr->toDesign(), 1024, QrCodeFormat::Png)->contents;
        $plainPng = $this->images()->render($qr->toDesign()->withoutLogo(), 1024, QrCodeFormat::Png)->contents;

        $this->assertNotSame($plainPng, $png);
        $this->assertSame(route('link_page.qr', $qr->code), QrCodeTestImages::decode($png));
    }

    public function test_a_logo_that_cannot_be_read_is_left_out_instead_of_failing(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_light', 'branding/missing.png');
        Storage::disk('images')->put('link-page/qr/broken.png', 'not an image');
        $site = $this->qrCode(['logo_source' => QrLogoSource::Site]);
        $custom = $this->qrCode(['logo_source' => QrLogoSource::Custom, 'logo_path' => 'link-page/qr/broken.png']);

        foreach ([$site, $custom] as $qr) {
            $png = $this->images()->render($qr->toDesign(), 512, QrCodeFormat::Png)->contents;

            $this->assertSame(route('link_page.qr', $qr->code), QrCodeTestImages::decode($png));
        }
    }

    private function images(): QrCodeImagesInterface
    {
        return app(QrCodeImagesInterface::class);
    }
}
