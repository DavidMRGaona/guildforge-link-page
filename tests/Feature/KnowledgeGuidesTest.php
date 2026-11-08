<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature;

use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Pages\KnowledgeBasePage;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Livewire;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Modules\LinkPage\Filament\Pages\LinkPageStatsPage;
use Modules\LinkPage\Filament\Resources\LinkResource;
use Modules\LinkPage\Filament\Resources\LinkResource\Pages\ListLinks;
use Modules\LinkPage\Filament\Resources\QrCodeResource;
use Modules\LinkPage\Filament\Resources\QrCodeResource\Pages\ListQrCodes;
use Modules\LinkPage\Tests\Support\CreatesLinks;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The module's help guides pass the knowledge base lint, show only to whoever may read what
 * they explain, and the pages they explain open them.
 */
final class KnowledgeGuidesTest extends ModuleTestCase
{
    use CreatesLinks;
    use CreatesQrCodes;
    use InteractsWithModulePanel;

    private const string STATS_GUIDE = 'link-page.panel.stats';

    private const string SETTINGS_GUIDE = 'link-page.panel.settings';

    private const string APPEARANCE_GUIDE = 'link-page.panel.appearance';

    private const string NOTICE_GUIDE = 'link-page.panel.notice-and-featured-event';

    private const string LINKS_GUIDE = 'link-page.panel.links-and-socials';

    private const string QR_GUIDE = 'link-page.panel.qr-codes';

    private const string SETTINGS = 'link-page:settings.manage';

    private const string QR_CODES = 'link-page:qr_codes.manage';

    private const string LINKS = 'link-page:links.manage';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        // The guide links to the statistics page, routed only once it is on the panel
        $this->registerOnAdminPanel(
            resources: [LinkResource::class, QrCodeResource::class],
            pages: [LinkPageSettingsPage::class, LinkPageStatsPage::class],
        );
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
        $this->assertNotContains(self::STATS_GUIDE, $this->queries()->visibleIds($this->userWith(['link-page:settings.manage']), GuideAudience::Panel));
    }

    public function test_the_statistics_page_opens_its_guide(): void
    {
        $this->syncGuides();
        $this->actingAs($this->userWith([LinkPageStatsPage::PERMISSION]));

        Livewire::test(LinkPageStatsPage::class)
            ->assertActionVisible('contextualHelp')
            ->assertActionHasUrl('contextualHelp', KnowledgeBasePage::getUrl(['guia' => self::STATS_GUIDE]));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function guides(): array
    {
        return [
            'settings' => [self::SETTINGS_GUIDE],
            'appearance' => [self::APPEARANCE_GUIDE],
            'notice and featured event' => [self::NOTICE_GUIDE],
            'own links and socials' => [self::LINKS_GUIDE],
            'qr codes' => [self::QR_GUIDE],
            'statistics' => [self::STATS_GUIDE],
        ];
    }

    #[DataProvider('guides')]
    public function test_every_guide_renders_without_raw_markup(string $guideId): void
    {
        $this->syncGuides();
        $admin = UserModel::factory()->admin()->create();

        $guide = $this->queries()->guide($admin, GuideAudience::Panel, GuideId::fromString($guideId));

        $this->assertNotNull($guide);
        foreach (['[[', '{{', '[!'] as $raw) {
            $this->assertStringNotContainsString($raw, $guide->html);
        }
    }

    public function test_the_guides_show_to_whoever_manages_what_they_explain(): void
    {
        $this->syncGuides();

        $settings = $this->queries()->visibleIds($this->userWith([self::SETTINGS]), GuideAudience::Panel);
        $this->assertContains(self::SETTINGS_GUIDE, $settings);
        $this->assertContains(self::APPEARANCE_GUIDE, $settings);
        $this->assertContains(self::NOTICE_GUIDE, $settings);
        $this->assertNotContains(self::QR_GUIDE, $settings);
        $this->assertNotContains(self::STATS_GUIDE, $settings);

        $qr = $this->queries()->visibleIds($this->userWith([self::QR_CODES]), GuideAudience::Panel);
        $this->assertContains(self::QR_GUIDE, $qr);
        $this->assertNotContains(self::SETTINGS_GUIDE, $qr);
        $this->assertNotContains(self::LINKS_GUIDE, $qr);
    }

    public function test_the_links_guide_also_serves_whoever_sets_the_social_row(): void
    {
        $this->syncGuides();

        // The own links are a list of their own, the social row a section of the settings
        $this->assertContains(self::LINKS_GUIDE, $this->queries()->visibleIds($this->userWith([self::LINKS]), GuideAudience::Panel));
        $this->assertContains(self::LINKS_GUIDE, $this->queries()->visibleIds($this->userWith([self::SETTINGS]), GuideAudience::Panel));
        $this->assertNotContains(self::LINKS_GUIDE, $this->queries()->visibleIds($this->userWith([self::QR_CODES]), GuideAudience::Panel));
    }

    public function test_the_guides_reach_each_other_and_only_the_modules_own(): void
    {
        $this->syncGuides();
        $admin = UserModel::factory()->admin()->create();

        $guide = $this->queries()->guide($admin, GuideAudience::Panel, GuideId::fromString(self::SETTINGS_GUIDE));

        $this->assertNotNull($guide);
        $this->assertNotEmpty($guide->related);
    }

    public function test_the_settings_page_opens_its_guide(): void
    {
        $this->syncGuides();
        $this->actingAs($this->userWith([self::SETTINGS]));

        Livewire::test(LinkPageSettingsPage::class)
            ->assertActionVisible('contextualHelp')
            ->assertActionHasUrl('contextualHelp', KnowledgeBasePage::getUrl(['guia' => self::SETTINGS_GUIDE]));
    }

    public function test_the_qr_codes_list_opens_its_guide(): void
    {
        $this->syncGuides();
        $this->actingAs($this->userWith([self::QR_CODES]));

        Livewire::test(ListQrCodes::class)
            ->assertActionVisible('contextualHelp')
            ->assertActionHasUrl('contextualHelp', KnowledgeBasePage::getUrl(['guia' => self::QR_GUIDE]));
    }

    public function test_the_own_links_list_opens_its_guide(): void
    {
        $this->syncGuides();
        $this->actingAs($this->userWithLinkPermissions([self::LINKS]));

        Livewire::test(ListLinks::class)
            ->assertActionVisible('contextualHelp')
            ->assertActionHasUrl('contextualHelp', KnowledgeBasePage::getUrl(['guia' => self::LINKS_GUIDE]));
    }

    public function test_the_help_button_keeps_the_create_button_on_the_lists(): void
    {
        $this->syncGuides();
        $this->actingAs($this->userWith([self::QR_CODES]));

        Livewire::test(ListQrCodes::class)->assertActionVisible('create');
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
