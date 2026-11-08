<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Infrastructure;

use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\Services\SettingsServiceInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\LinkPage\Application\Contracts\QrCodeImagesInterface;
use Modules\LinkPage\Application\DTOs\QrCodeDesignDTO;
use Modules\LinkPage\Domain\Enums\QrLogoSource;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\QrCodes\QrCodeTestImages;

/**
 * The images people print: they must always decode to the stable /qr/{code} address.
 *
 * The PHP decoder misses a few valid codes (about one in twenty at 512 px; the ones it
 * missed were read by a browser's barcode detector), so the content is pinned: the same
 * site address and code on every machine give the same images, and these decode.
 */
final class QrCodeImagesTest extends ModuleTestCase
{
    use CreatesQrCodes;

    private const string SITE = 'http://localhost';

    private const string CODE = 'k7m2xq';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        config()->set('app.url', self::SITE);
        URL::forceRootUrl(self::SITE);
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
        $qr = $this->pinnedQrCode();

        $png = $this->images()->render($qr->toDesign(), $size, QrCodeFormat::Png);

        $this->assertSame([$size, $size], array_slice((array) getimagesizefromstring($png->contents), 0, 2));
        $this->assertSame(route('link_page.qr', $qr->code), QrCodeTestImages::decode($png->contents));
        $this->assertStringEndsWith('/qr/'.$qr->code, QrCodeTestImages::decode($png->contents));
    }

    public function test_the_svg_is_a_drawing_of_the_same_code(): void
    {
        $svg = $this->images()->render($this->pinnedQrCode()->toDesign(), 1024, QrCodeFormat::Svg);

        $this->assertStringContainsString('<svg', $svg->contents);
        $this->assertSame('image/svg+xml', $svg->mimeType());
    }

    public function test_it_uses_the_colours_and_the_margin_of_the_qr_code(): void
    {
        $qr = $this->pinnedQrCode(['foreground_color' => '#1F2937', 'background_color' => '#FFF8E7', 'margin' => 10]);

        $png = $this->images()->render($qr->toDesign(), 512, QrCodeFormat::Png)->contents;

        // The margin (10 % of 512 px, about 51 px) is all background
        $this->assertSame([0xFF, 0xF8, 0xE7], QrCodeTestImages::pixel($png, 45, 45));
        $this->assertSame(route('link_page.qr', $qr->code), QrCodeTestImages::decode($png));
    }

    public function test_the_site_logo_goes_in_the_centre_and_the_code_still_decodes(): void
    {
        Storage::disk('images')->put('branding/logo.png', QrCodeTestImages::png(200, 200));
        app(SettingsServiceInterface::class)->set('site_logo_light', 'branding/logo.png');
        $withLogo = $this->pinnedQrCode(['logo_source' => QrLogoSource::Site]);

        $png = $this->images()->render($withLogo->toDesign(), 1024, QrCodeFormat::Png)->contents;
        $plainPng = $this->images()->render($withLogo->toDesign()->withoutLogo(), 1024, QrCodeFormat::Png)->contents;

        $this->assertNotSame($plainPng, $png);
        $this->assertSame(route('link_page.qr', $withLogo->code), QrCodeTestImages::decode($png));
    }

    public function test_an_uploaded_logo_goes_in_the_centre(): void
    {
        Storage::disk('images')->put('link-page/qr/own.png', QrCodeTestImages::png(120, 80));
        $qr = $this->pinnedQrCode(['logo_source' => QrLogoSource::Custom, 'logo_path' => 'link-page/qr/own.png']);

        $png = $this->images()->render($qr->toDesign(), 1024, QrCodeFormat::Png)->contents;
        $plainPng = $this->images()->render($qr->toDesign()->withoutLogo(), 1024, QrCodeFormat::Png)->contents;

        $this->assertNotSame($plainPng, $png);
        $this->assertSame(route('link_page.qr', $qr->code), QrCodeTestImages::decode($png));
    }

    /**
     * The usual site logo is an SVG: it goes in the SVG download, sanitized, and in the PNG.
     */
    public function test_an_svg_site_logo_goes_in_the_svg_and_in_the_png(): void
    {
        Storage::disk('images')->put('branding/logo.svg', QrCodeTestImages::svg(200, 100, '<script>alert(1)</script>'));
        app(SettingsServiceInterface::class)->set('site_logo_light', 'branding/logo.svg');
        $qr = $this->pinnedQrCode(['logo_source' => QrLogoSource::Site]);

        $svg = $this->images()->render($qr->toDesign(), 1024, QrCodeFormat::Svg)->contents;
        $logo = $this->embeddedSvgLogo($svg);
        $this->assertStringContainsString('#C81E1E', $logo);
        $this->assertStringNotContainsString('script', $logo);

        $png = $this->images()->render($qr->toDesign(), 1024, QrCodeFormat::Png)->contents;
        $plainPng = $this->images()->render($qr->toDesign()->withoutLogo(), 1024, QrCodeFormat::Png)->contents;
        $this->assertNotSame($plainPng, $png);
        $this->assertSame(route('link_page.qr', $qr->code), QrCodeTestImages::decode($png));
    }

    public function test_an_uploaded_svg_logo_goes_in_the_centre(): void
    {
        Storage::disk('images')->put('link-page/qr/own.svg', QrCodeTestImages::svg(120, 80));
        $qr = $this->pinnedQrCode(['logo_source' => QrLogoSource::Custom, 'logo_path' => 'link-page/qr/own.svg']);

        $this->embeddedSvgLogo($this->images()->render($qr->toDesign(), 1024, QrCodeFormat::Svg)->contents);
        $png = $this->images()->render($qr->toDesign(), 1024, QrCodeFormat::Png)->contents;
        $plainPng = $this->images()->render($qr->toDesign()->withoutLogo(), 1024, QrCodeFormat::Png)->contents;
        $this->assertNotSame($plainPng, $png);
        $this->assertSame(route('link_page.qr', $qr->code), QrCodeTestImages::decode($png));
    }

    /**
     * @return iterable<string, array{string|null, string|null, bool}>
     */
    public static function siteLogos(): iterable
    {
        yield 'no site logo' => [null, null, false];
        yield 'a PNG' => ['branding/logo.png', QrCodeTestImages::png(120, 120), false];
        yield 'an SVG' => ['branding/logo.svg', QrCodeTestImages::svg(120, 120), false];
        yield 'an SVG with a DOCTYPE' => ['branding/logo.svg', '<!DOCTYPE svg>'.QrCodeTestImages::svg(120, 120), true];
        yield 'an SVG sized in percent' => ['branding/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"/>', true];
        yield 'not an image' => ['branding/logo.png', 'not an image', true];
        yield 'a missing file' => ['branding/missing.png', null, true];
    }

    #[DataProvider('siteLogos')]
    public function test_it_tells_when_the_site_has_a_logo_qr_codes_cannot_use(?string $path, ?string $contents, bool $unusable): void
    {
        if ($path !== null && $contents !== null) {
            Storage::disk('images')->put($path, $contents);
        }
        if ($path !== null) {
            app(SettingsServiceInterface::class)->set('site_logo_light', $path);
        }

        $this->assertSame($unusable, $this->images()->siteLogoIsUnusable());
    }

    public function test_a_logo_that_cannot_be_read_is_left_out_instead_of_failing(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_light', 'branding/missing.png');
        Storage::disk('images')->put('link-page/qr/broken.png', 'not an image');
        $site = $this->pinnedQrCode(['logo_source' => QrLogoSource::Site]);
        $custom = $this->pinnedQrCode(['logo_source' => QrLogoSource::Custom, 'logo_path' => 'link-page/qr/broken.png']);

        foreach ([$site, $custom] as $qr) {
            $png = $this->images()->render($qr->toDesign(), 512, QrCodeFormat::Png)->contents;

            $this->assertSame(route('link_page.qr', $qr->code), QrCodeTestImages::decode($png));
        }
    }

    public function test_a_custom_logo_is_only_read_from_the_qr_logo_directory(): void
    {
        Storage::disk('images')->put('branding/other.png', QrCodeTestImages::png(120, 80));
        $qr = $this->pinnedQrCode();
        $design = new QrCodeDesignDTO(
            code: $qr->code,
            foregroundColor: '#000000',
            backgroundColor: '#FFFFFF',
            marginPercent: 8,
            logoSource: QrLogoSource::Custom,
            logoPath: 'branding/other.png',
        );

        $this->assertSame(
            $this->images()->render($design->withoutLogo(), 512, QrCodeFormat::Png)->contents,
            $this->images()->render($design, 512, QrCodeFormat::Png)->contents,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pinnedQrCode(array $attributes = []): QrCodeModel
    {
        QrCodeModel::withTrashed()->where('code', self::CODE)->forceDelete();

        $qr = $this->qrCode($attributes);
        $qr->forceFill(['code' => self::CODE])->save();

        return $qr;
    }

    /**
     * The SVG logo a drawn code carries, as a data URI image in the centre.
     */
    private function embeddedSvgLogo(string $svg): string
    {
        $this->assertSame(1, preg_match('#<image[^>]+href="data:image/svg\+xml;base64,([A-Za-z0-9+/=]+)"#', $svg, $match));

        return (string) base64_decode($match[1], true);
    }

    private function images(): QrCodeImagesInterface
    {
        return app(QrCodeImagesInterface::class);
    }
}
