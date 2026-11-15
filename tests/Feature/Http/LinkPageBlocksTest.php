<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use App\Application\SiteBlocks\DTOs\ResolvedSiteBlockDTO;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Application\SiteBlocks\Services\SiteBlockRendererInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\SiteBlocks\FakeSiteBlock;

/**
 * The site blocks of /enlaces (link-page#48): each stored block is resolved on the server for the
 * person viewing, through the core's renderer, and one that cannot be drawn is simply left out.
 */
final class LinkPageBlocksTest extends ModuleTestCase
{
    private const string OWNER = 'example-module';

    private const string KEY = 'example-module.subscribe';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));
    }

    public function test_without_blocks_the_page_sends_none(): void
    {
        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('blocks', []));
    }

    public function test_a_malformed_stored_value_is_no_block(): void
    {
        $this->registerSubscribe();
        config()->set('modules.settings.link-page.blocks', 'example-module.subscribe');

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('blocks', []));
    }

    public function test_it_resolves_each_stored_block_in_order_with_its_title(): void
    {
        $this->registerSubscribe();
        config()->set('modules.settings.link-page.blocks', [
            ['key' => self::KEY, 'title' => 'Boletín', 'settings' => ['heading' => 'Suscríbete']],
            ['key' => self::KEY, 'title' => null, 'settings' => ['heading' => 'Otra vez']],
        ]);

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('blocks', 2)
                ->where('blocks.0.title', 'Boletín')
                ->where('blocks.0.label', 'Bloque de ejemplo')
                ->where('blocks.0.block.key', self::KEY)
                ->where('blocks.0.block.module', self::OWNER)
                ->where('blocks.0.block.component', 'components/ExampleBlock.vue')
                ->where('blocks.0.block.props.settings', ['heading' => 'Suscríbete'])
                ->where('blocks.0.block.props.signedIn', false)
                ->where('blocks.1.title', null)
                ->where('blocks.1.block.props.settings', ['heading' => 'Otra vez'])
            );
    }

    public function test_blocks_are_resolved_for_the_person_viewing(): void
    {
        $this->registerSubscribe();
        config()->set('modules.settings.link-page.blocks', [['key' => self::KEY, 'title' => null, 'settings' => []]]);

        $this->actingAs(UserModel::factory()->create())
            ->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('blocks.0.block.props.signedIn', true));
    }

    public function test_the_page_asks_the_renderer_for_the_link_page_placement(): void
    {
        config()->set('modules.settings.link-page.blocks', [['key' => self::KEY, 'title' => null, 'settings' => ['heading' => 'Hola']]]);
        $this->mock(SiteBlockRendererInterface::class, function (MockInterface $renderer): void {
            $renderer->shouldReceive('resolveBlock')
                ->once()
                ->withArgs(static fn (string $key, array $settings, SiteBlockPlacement $placement, ?Authenticatable $user): bool => $key === self::KEY
                    && $settings === ['heading' => 'Hola']
                    && $placement === SiteBlockPlacement::LinkPage
                    && ! $user instanceof Authenticatable)
                ->andReturn(new ResolvedSiteBlockDTO(self::KEY, self::OWNER, 'components/ExampleBlock.vue', ['heading' => 'Hola']));
        });

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('blocks', 1)
                ->where('blocks.0.block.props.heading', 'Hola')
            );
    }

    public function test_blocks_that_cannot_be_drawn_are_left_out_and_the_rest_are_drawn(): void
    {
        $this->registerSubscribe();
        $this->registerClass(new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'example-module.unavailable';
            }

            public function placements(): array
            {
                return [SiteBlockPlacement::LinkPage];
            }

            public function isAvailable(): bool
            {
                return false;
            }
        });
        $this->registerClass(new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'example-module.footer_only';
            }
        });
        $this->registerClass(new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'example-module.throws';
            }

            public function placements(): array
            {
                return [SiteBlockPlacement::LinkPage];
            }

            public function props(array $settings, ?Authenticatable $user): array
            {
                throw new RuntimeException('Provider down');
            }
        });
        // Nobody registers it, it cannot be drawn now, it is not offered for the link page, it throws
        foreach (['gone-module.subscribe', 'example-module.unavailable', 'example-module.footer_only', 'example-module.throws'] as $key) {
            config()->set('modules.settings.link-page.blocks', [
                ['key' => $key, 'title' => 'No sale', 'settings' => []],
                ['key' => self::KEY, 'title' => 'Boletín', 'settings' => []],
            ]);

            $this->get('/enlaces')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('blocks', 1)
                    ->where('blocks.0.title', 'Boletín')
                    ->where('blocks.0.block.key', self::KEY)
                );
        }
    }

    public function test_a_renderer_that_throws_leaves_the_page_without_blocks(): void
    {
        config()->set('modules.settings.link-page.blocks', [['key' => self::KEY, 'title' => null, 'settings' => []]]);
        $this->mock(SiteBlockRendererInterface::class, static function (MockInterface $renderer): void {
            $renderer->shouldReceive('resolveBlock')->andThrow(new RuntimeException('Renderer down'));
        });

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('blocks', []));
    }

    private function registerSubscribe(): void
    {
        $this->registerClass(new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'example-module.subscribe';
            }

            public function placements(): array
            {
                return [SiteBlockPlacement::Footer, SiteBlockPlacement::LinkPage];
            }

            public function settingsSchema(): array
            {
                return [TextInput::make('heading')];
            }
        });
    }

    private function registerClass(FakeSiteBlock $block): void
    {
        app(SiteBlockRegistryInterface::class)->register($block::class, self::OWNER);
    }
}
