<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament\Media;

use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\DTOs\MediaUsageDTO;
use App\Domain\Media\ValueObjects\MediaFileId;
use Modules\LinkPage\Filament\Media\Sources\SettingsMediaSource;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use ReflectionClass;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class SettingsMediaSourceTest extends ModuleTestCase
{
    use CreatesQrCodes;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    private const string KEY = 'modules.settings.link-page';

    private function source(): SettingsMediaSource
    {
        return app(SettingsMediaSource::class);
    }

    private function images(?string $logo = null, ?string $background = null, ?string $share = null): void
    {
        config([
            self::KEY.'.appearance.logo.image' => $logo,
            self::KEY.'.appearance.background.image' => $background,
            self::KEY.'.share.image' => $share,
        ]);
    }

    public function test_it_is_a_prefixed_source_claiming_the_module_folder_but_not_the_qr_one(): void
    {
        $source = $this->source();

        $this->assertSame('link-page.settings', $source->type());
        $this->assertSame(['link-page/'], $source->folders());
        $this->assertSame(['qr/'], $source->excludedSubfolders());
        $this->assertFalse($source->hidden());
    }

    public function test_without_images_there_are_no_usages(): void
    {
        $this->images();

        $this->assertSame([], iterator_to_array($this->source()->usages(), false));
    }

    public function test_usages_list_logo_background_and_share_image_with_role_and_record(): void
    {
        $this->images('link-page/2026/11/logo.png', 'link-page/2026/11/bg.jpg', 'link-page/share/2026/11/card.png');

        $usages = iterator_to_array($this->source()->usages(), false);

        $this->assertCount(3, $usages);
        $byRecord = [];
        foreach ($usages as $usage) {
            $byRecord[$usage->recordId] = $usage;
        }
        $this->assertSame('link-page/2026/11/logo.png', $byRecord['appearance.logo']->storedPath);
        $this->assertSame('Logo', $byRecord['appearance.logo']->role);
        $this->assertSame('link-page/2026/11/bg.jpg', $byRecord['appearance.background']->storedPath);
        $this->assertSame('Fondo', $byRecord['appearance.background']->role);
        $this->assertSame('link-page/share/2026/11/card.png', $byRecord['share']->storedPath);
        $this->assertSame('Compartir', $byRecord['share']->role);
        $this->assertSame('Ajustes de la página de enlaces', $byRecord['share']->title);
    }

    public function test_usages_for_one_record_and_unknown_records(): void
    {
        $this->images(null, 'link-page/2026/11/bg.jpg');

        $this->assertCount(1, $this->source()->usagesFor('appearance.background'));
        $this->assertSame([], $this->source()->usagesFor('appearance.logo'));
        $this->assertSame([], $this->source()->usagesFor('share'));
        $this->assertSame([], $this->source()->usagesFor('enabled'));
    }

    /**
     * An image chosen from the library stays a use of that file, so the core never deletes it
     * while the page shows it.
     */
    public function test_an_image_chosen_from_the_library_is_a_usage(): void
    {
        $this->images('events/2026/11/other.png', null, 'branding/logo.png');

        $paths = array_map(static fn (MediaUsageDTO $usage): string => $usage->storedPath, iterator_to_array($this->source()->usages(), false));

        $this->assertSame(['events/2026/11/other.png', 'branding/logo.png'], $paths);
        $this->assertTrue($this->source()->usesFile(new MediaFileId('branding/logo')));
    }

    public function test_a_stored_path_the_page_cannot_use_is_ignored(): void
    {
        $this->images('events/2026/11/my other.png', null, 'branding/logo.webp');

        $this->assertSame([], iterator_to_array($this->source()->usages(), false));
    }

    public function test_it_lets_the_core_release_its_files(): void
    {
        // Without the marker the library would neither offer its files nor attach the picker to its fields
        $this->assertTrue((new ReflectionClass(SettingsMediaSource::class))->implementsInterface(ReleasesMediaThroughCoreInterface::class));
    }

    public function test_uses_file_matches_only_the_exact_id(): void
    {
        $this->images('link-page/2026/11/logo.png', null, 'link-page/share/card.jpg');

        $this->assertTrue($this->source()->usesFile(new MediaFileId('link-page/2026/11/logo')));
        $this->assertTrue($this->source()->usesFile(new MediaFileId('link-page/share/card')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('link-page/2026/11/logo2')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('link-page/2026/11/LOGO')));
    }

    public function test_edit_url_points_to_the_settings_page_only_with_permission(): void
    {
        $this->registerOnAdminPanel([], [LinkPageSettingsPage::class]);
        $manager = $this->userWith(['link-page:settings.manage']);
        $other = $this->userWith(['link-page:qr_codes.manage']);

        foreach (['appearance.logo', 'appearance.background', 'share'] as $record) {
            $this->assertSame(LinkPageSettingsPage::getUrl(), $this->source()->editUrl($record, $manager));
            $this->assertNull($this->source()->editUrl($record, $other));
        }
        $this->assertNull($this->source()->editUrl('unknown', $manager));
    }
}
