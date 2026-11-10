<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament\Media;

use App\Domain\Media\ValueObjects\MediaFileId;
use Modules\LinkPage\Domain\Enums\QrLogoSource;
use Modules\LinkPage\Filament\Media\Sources\QrLogoMediaSource;
use Modules\LinkPage\Filament\Resources\QrCodeResource;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class QrLogoMediaSourceTest extends ModuleTestCase
{
    use CreatesQrCodes;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    private function source(): QrLogoMediaSource
    {
        return app(QrLogoMediaSource::class);
    }

    private function withLogo(string $path = 'link-page/qr/2026/11/logo.png', string $name = 'Venue poster'): QrCodeModel
    {
        return $this->qrCode(['name' => $name, 'logo_source' => QrLogoSource::Custom, 'logo_path' => $path]);
    }

    public function test_it_is_a_prefixed_source_claiming_the_qr_folder(): void
    {
        $source = $this->source();

        $this->assertSame('link-page.qr_logos', $source->type());
        $this->assertSame(['link-page/qr/'], $source->folders());
        $this->assertFalse($source->hidden());
    }

    public function test_usages_list_the_logo_with_the_qr_name_and_role(): void
    {
        $qr = $this->withLogo();
        $this->qrCode(['name' => 'No logo']);

        $usages = iterator_to_array($this->source()->usages(), false);

        $this->assertCount(1, $usages);
        $this->assertSame((string) $qr->getKey(), $usages[0]->recordId);
        $this->assertSame('link-page/qr/2026/11/logo.png', $usages[0]->storedPath);
        $this->assertSame('Logo del QR', $usages[0]->role);
        $this->assertSame('Venue poster', $usages[0]->title);
    }

    public function test_a_qr_in_the_trash_still_uses_its_logo(): void
    {
        $qr = $this->withLogo();
        $qr->delete();

        $this->assertCount(1, iterator_to_array($this->source()->usages(), false));
        $this->assertCount(1, $this->source()->usagesFor((string) $qr->getKey()));
        $this->assertTrue($this->source()->usesFile(new MediaFileId('link-page/qr/2026/11/logo')));
    }

    public function test_uses_file_matches_only_the_exact_id(): void
    {
        $this->withLogo();

        $this->assertTrue($this->source()->usesFile(new MediaFileId('link-page/qr/2026/11/logo')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('link-page/qr/2026/11/logo2')));
    }

    public function test_usages_for_unknown_or_malformed_ids_are_empty(): void
    {
        $this->assertSame([], $this->source()->usagesFor('not-a-uuid'));
        $this->assertSame([], $this->source()->usagesFor('0194f5a0-0000-7000-8000-000000000000'));
    }

    public function test_edit_url_points_to_the_qr_only_with_permission(): void
    {
        $this->registerOnAdminPanel([QrCodeResource::class], []);
        $qr = $this->withLogo();
        $manager = $this->userWith(['link-page:qr_codes.manage']);
        $other = $this->userWith(['link-page:settings.manage']);
        $id = (string) $qr->getKey();

        $this->assertSame(QrCodeResource::getUrl('edit', ['record' => $qr]), $this->source()->editUrl($id, $manager));
        $this->assertNull($this->source()->editUrl($id, $other));
        $this->assertNull($this->source()->editUrl('not-a-uuid', $manager));
    }
}
