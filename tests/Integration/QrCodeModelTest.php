<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Modules\LinkPage\Application\Contracts\QrScanRecorderInterface;
use Modules\LinkPage\Domain\Enums\QrLogoSource;
use Modules\LinkPage\Domain\ValueObjects\ScanCode;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Modules\LinkPage\Infrastructure\Services\NullQrScanRecorder;
use Modules\LinkPage\Infrastructure\Services\ScanCodeGenerator;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use PHPUnit\Framework\Attributes\DataProvider;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Support\Modules\ModuleTestCase;

final class QrCodeModelTest extends ModuleTestCase
{
    use CreatesQrCodes;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    public function test_a_new_qr_code_gets_its_code_and_the_defaults(): void
    {
        $qr = $this->qrCode()->refresh();

        $this->assertTrue(ScanCode::isValid($qr->code));
        $this->assertTrue($qr->is_active);
        $this->assertSame('#000000', $qr->foreground_color);
        $this->assertSame('#FFFFFF', $qr->background_color);
        $this->assertSame(QrCodeModel::DEFAULT_MARGIN, $qr->margin);
        $this->assertSame(QrLogoSource::None, $qr->logo_source);
        $this->assertNull($qr->logo_path);
        $this->assertNull($qr->tagline);
    }

    public function test_the_code_cannot_be_set_from_the_form_data(): void
    {
        $qr = $this->qrCode(['code' => 'aaaaaa']);

        $this->assertNotSame('aaaaaa', $qr->code);
    }

    public function test_a_code_once_used_is_never_given_again_even_after_deleting_it(): void
    {
        $taken = ScanCode::generate(new Randomizer(new Mt19937(7)))->value;
        $old = $this->qrCode();
        $old->forceFill(['code' => $taken])->save();
        $old->delete();

        $this->app->instance(ScanCodeGenerator::class, new ScanCodeGenerator(new Randomizer(new Mt19937(7))));
        $new = $this->qrCode();

        $this->assertNotSame($taken, $new->code);
        $this->assertTrue(ScanCode::isValid($new->code));
    }

    public function test_the_database_refuses_a_repeated_code_even_for_deleted_rows(): void
    {
        $old = $this->qrCode();
        $old->delete();

        $this->expectException(QueryException::class);

        $this->qrCode()->forceFill(['code' => $old->code])->save();
    }

    public function test_the_previous_logo_is_removed_when_it_changes(): void
    {
        Storage::fake('images');
        Storage::disk('images')->put('link-page/qr/old.png', 'old');
        Storage::disk('images')->put('link-page/qr/new.png', 'new');
        $qr = $this->qrCode(['logo_source' => QrLogoSource::Custom, 'logo_path' => 'link-page/qr/old.png']);

        $qr->update(['logo_path' => 'link-page/qr/new.png']);

        Storage::disk('images')->assertMissing('link-page/qr/old.png');
        Storage::disk('images')->assertExists('link-page/qr/new.png');
    }

    public function test_choosing_another_logo_source_drops_the_uploaded_logo(): void
    {
        Storage::fake('images');
        Storage::disk('images')->put('link-page/qr/own.png', 'own');
        $qr = $this->qrCode(['logo_source' => QrLogoSource::Custom, 'logo_path' => 'link-page/qr/own.png']);

        $qr->update(['logo_source' => QrLogoSource::Site]);

        $this->assertNull($qr->refresh()->logo_path);
        Storage::disk('images')->assertMissing('link-page/qr/own.png');
    }

    public function test_deleting_a_qr_code_removes_its_logo(): void
    {
        Storage::fake('images');
        Storage::disk('images')->put('link-page/qr/own.png', 'own');
        $qr = $this->qrCode(['logo_source' => QrLogoSource::Custom, 'logo_path' => 'link-page/qr/own.png']);

        $qr->delete();

        Storage::disk('images')->assertMissing('link-page/qr/own.png');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function foreignPaths(): array
    {
        return [
            'the site logo' => ['branding/logo.png'],
            'outside through dots' => ['link-page/qr/../../branding/logo.png'],
            'the directory itself' => ['link-page/qr'],
            'a look-alike directory' => ['link-page/qrx/logo.png'],
            'an absolute path' => ['/link-page/qr/logo.png'],
        ];
    }

    /**
     * A forged form state must not point the QR at another file of the images disk, nor
     * get it deleted when the logo changes or the QR goes.
     */
    #[DataProvider('foreignPaths')]
    public function test_a_logo_path_outside_the_qr_logo_directory_is_never_kept_nor_deleted(string $path): void
    {
        Storage::fake('images');
        Storage::disk('images')->put('branding/logo.png', 'site');

        $qr = $this->qrCode(['logo_source' => QrLogoSource::Custom, 'logo_path' => $path]);
        $this->assertNull($qr->refresh()->logo_path);

        $qr->forceFill(['logo_path' => $path])->saveQuietly();
        $qr->update(['logo_path' => null]);
        $qr->forceFill(['logo_path' => $path])->saveQuietly();
        $qr->delete();

        Storage::disk('images')->assertExists('branding/logo.png');
    }

    public function test_scans_are_not_counted_until_the_statistics_arrive(): void
    {
        $this->assertInstanceOf(NullQrScanRecorder::class, app(QrScanRecorderInterface::class));
    }
}
