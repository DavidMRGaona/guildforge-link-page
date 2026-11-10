<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament\Media;

use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use Livewire\Livewire;
use Modules\LinkPage\Domain\Enums\QrLogoSource;
use Modules\LinkPage\Filament\Media\Sources\QrLogoMediaSource;
use Modules\LinkPage\Filament\Media\Sources\SettingsMediaSource;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Modules\LinkPage\LinkPageServiceProvider;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use Modules\LinkPage\Tests\Support\RecordingMediaIndexer;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class MediaSourcesRegistrationTest extends ModuleTestCase
{
    use CreatesQrCodes;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    public function test_the_provider_registers_both_sources(): void
    {
        $provider = new LinkPageServiceProvider($this->app);

        $this->assertSame([SettingsMediaSource::class, QrLogoMediaSource::class], $provider->registerMediaSources());
    }

    public function test_the_core_registry_knows_them_without_duplicated_types(): void
    {
        $registry = app(MediaSourceRegistryInterface::class);
        $types = array_map(static fn ($source): string => $source->type(), $registry->sources());

        $this->assertContains('link-page.settings', $types);
        $this->assertContains('link-page.qr_logos', $types);
        $this->assertSame($types, array_values(array_unique($types)));
        $this->assertSame([], $registry->unresolved());
    }

    public function test_saving_and_deleting_a_qr_with_a_logo_refreshes_its_usages(): void
    {
        $indexer = new RecordingMediaIndexer;
        $this->app->instance(MediaIndexerInterface::class, $indexer);

        $qr = $this->qrCode(['logo_source' => QrLogoSource::Custom, 'logo_path' => 'link-page/qr/2026/11/logo.png']);
        $id = (string) $qr->getKey();
        $this->assertContains('link-page.qr_logos|'.$id, $indexer->calls);

        $indexer->calls = [];
        $qr->delete();
        $this->assertContains('link-page.qr_logos|'.$id, $indexer->calls);
    }

    public function test_saving_the_settings_refreshes_the_appearance_and_share_usages(): void
    {
        $indexer = new RecordingMediaIndexer;
        $this->app->instance(MediaIndexerInterface::class, $indexer);
        $this->registerOnAdminPanel([], [LinkPageSettingsPage::class]);
        $this->actingAsPanelAdmin();

        Livewire::test(LinkPageSettingsPage::class)->call('save')->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            ['link-page.settings|appearance.logo', 'link-page.settings|appearance.background', 'link-page.settings|share'],
            array_values(array_unique($indexer->calls)),
        );
    }
}
