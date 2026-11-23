<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament\Media;

use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Services\MediaLibraryQueryInterface;
use Modules\LinkPage\Domain\Enums\QrLogoSource;
use Modules\LinkPage\Filament\Media\Sources\QrLogoMediaSource;
use Modules\LinkPage\Filament\Media\Sources\SettingsMediaSource;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use ReflectionClass;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Every image of the link page may be seen by whoever opens the media library: the page is public, and its
 * settings and QR designs hold nothing that only some people may see (core#837).
 */
final class MediaPreviewsTest extends ModuleTestCase
{
    use CreatesQrCodes;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    private const string KEY = 'modules.settings.link-page';

    private InMemoryMediaCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $this->catalog);
    }

    public function test_both_sources_show_their_previews(): void
    {
        $this->assertTrue((new ReflectionClass(SettingsMediaSource::class))->implementsInterface(ShowsMediaPreviews::class));
        $this->assertTrue((new ReflectionClass(QrLogoMediaSource::class))->implementsInterface(ShowsMediaPreviews::class));
    }

    public function test_the_settings_images_are_shown_in_the_library(): void
    {
        config([
            self::KEY.'.appearance.logo.image' => 'link-page/2026/11/logo.png',
            self::KEY.'.appearance.background.image' => 'link-page/2026/11/bg.jpg',
            self::KEY.'.share.image' => 'link-page/share/2026/11/card.png',
        ]);

        $usages = iterator_to_array(app(SettingsMediaSource::class)->usages(), false);
        $this->assertCount(3, $usages);
        $this->assertSame([false, false, false], array_map(static fn ($usage): bool => $usage->hidden, $usages));

        $ids = $this->inCatalog('link-page/2026/11/logo.png', 'link-page/2026/11/bg.jpg', 'link-page/share/2026/11/card.png');
        $this->sync();

        foreach ($ids as $id) {
            $this->assertFalse($this->conceals($id), $id.' should show its preview');
        }
    }

    public function test_a_qr_logo_is_shown_whether_the_qr_is_active_inactive_or_in_the_trash(): void
    {
        // A QR design is the team's own work, printed on posters: switching it off or binning it hides nothing
        $this->withLogo('link-page/qr/2026/11/active.png');
        $this->withLogo('link-page/qr/2026/11/inactive.png', ['is_active' => false]);
        $this->withLogo('link-page/qr/2026/11/trashed.png')->delete();

        $usages = iterator_to_array(app(QrLogoMediaSource::class)->usages(), false);
        $this->assertCount(3, $usages);
        $this->assertSame([false, false, false], array_map(static fn ($usage): bool => $usage->hidden, $usages));

        $ids = $this->inCatalog('link-page/qr/2026/11/active.png', 'link-page/qr/2026/11/inactive.png', 'link-page/qr/2026/11/trashed.png');
        $this->sync();

        foreach ($ids as $id) {
            $this->assertFalse($this->conceals($id), $id.' should show its preview');
        }
    }

    public function test_an_unused_image_of_the_module_folders_keeps_its_preview(): void
    {
        // What gets reviewed before deleting must be seen
        $ids = $this->inCatalog('link-page/2026/10/old-logo.png', 'link-page/qr/2026/10/old-qr.png');
        $this->sync();

        foreach ($ids as $id) {
            $this->assertFalse($this->conceals($id), $id.' should show its preview');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function withLogo(string $path, array $attributes = []): QrCodeModel
    {
        return $this->qrCode([...$attributes, 'logo_source' => QrLogoSource::Custom, 'logo_path' => $path]);
    }

    /**
     * Puts the stored paths in the remote store and returns their file ids.
     *
     * @return list<string>
     */
    private function inCatalog(string ...$paths): array
    {
        $resolver = app(MediaFileIdResolverInterface::class);
        $ids = [];

        foreach ($paths as $path) {
            $id = $resolver->fromStoredPath($path);
            $this->assertInstanceOf(MediaFileId::class, $id);
            $this->catalog->add($id->value);
            $ids[] = $id->value;
        }

        return $ids;
    }

    private function sync(): void
    {
        $result = app(MediaIndexerInterface::class)->sync();

        $this->assertTrue($result->catalogComplete);
    }

    private function conceals(string $id): bool
    {
        $file = MediaFileModel::query()->where('file_id', $id)->firstOrFail();

        return app(MediaLibraryQueryInterface::class)->conceals($file);
    }
}
