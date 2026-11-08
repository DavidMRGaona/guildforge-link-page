<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature;

use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Pages\KnowledgeBasePage;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Livewire;
use Modules\LinkPage\Filament\Pages\LinkPageStatsPage;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The module's help guides pass the knowledge base lint, show only to whoever may read what
 * they explain, and the statistics page opens its guide.
 */
final class KnowledgeGuidesTest extends ModuleTestCase
{
    use CreatesQrCodes;
    use InteractsWithModulePanel;

    private const string STATS_GUIDE = 'link-page.panel.stats';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        // The guide links to the statistics page, routed only once it is on the panel
        $this->registerOnAdminPanel(pages: [LinkPageStatsPage::class]);
    }

    public function test_guides_are_valid(): void
    {
        $this->assertModuleKnowledgeGuidesAreValid('link-page');
    }

    public function test_the_statistics_guide_renders_without_raw_markup(): void
    {
        $this->syncGuides();
        $admin = UserModel::factory()->admin()->create();

        $guide = $this->queries()->guide($admin, GuideAudience::Panel, GuideId::fromString(self::STATS_GUIDE));

        $this->assertNotNull($guide);
        foreach (['[[', '{{', '[!'] as $raw) {
            $this->assertStringNotContainsString($raw, $guide->html);
        }
    }

    public function test_only_whoever_reads_the_statistics_sees_their_guide(): void
    {
        $this->syncGuides();

        $this->assertContains(self::STATS_GUIDE, $this->queries()->visibleIds($this->userWith([LinkPageStatsPage::PERMISSION]), GuideAudience::Panel));
        $this->assertNotContains(self::STATS_GUIDE, $this->queries()->visibleIds($this->userWith(['link-page:manage']), GuideAudience::Panel));
    }

    public function test_the_statistics_page_opens_its_guide(): void
    {
        $this->syncGuides();
        $this->actingAs($this->userWith([LinkPageStatsPage::PERMISSION]));

        Livewire::test(LinkPageStatsPage::class)
            ->assertActionVisible('contextualHelp')
            ->assertActionHasUrl('contextualHelp', KnowledgeBasePage::getUrl(['guia' => self::STATS_GUIDE]));
    }

    private function syncGuides(): void
    {
        $this->artisanCommand('knowledge:sync')->assertSuccessful()->run();
    }

    private function queries(): KnowledgeQueryServiceInterface
    {
        return $this->app->make(KnowledgeQueryServiceInterface::class);
    }
}
