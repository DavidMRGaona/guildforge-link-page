<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament;

use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\TextInput;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Domain\ValueObjects\PageBlock;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use RuntimeException;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\SiteBlocks\FakeSiteBlock;

/**
 * The «Bloques» section of the link page settings (link-page#48): blocks other modules offer for the
 * `link_page` placement, each with an optional title and its own settings, stored under `blocks`.
 * Only blocks that can be drawn now can be added; stored ones are never lost on saving.
 */
final class LinkPageBlocksFormTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const string OWNER = 'example-module';

    private const string KEY = 'example-module.subscribe';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([], [LinkPageSettingsPage::class]);
        $this->actingAsPanelAdmin();
    }

    public function test_it_offers_the_blocks_of_the_link_page_placement_only(): void
    {
        $this->registerSubscribe(available: true);
        $this->registerClass(new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'example-module.footer_only';
            }
        });

        $builder = $this->builderOf(Livewire::test(LinkPageSettingsPage::class));

        $this->assertSame([self::KEY], array_map(static fn (Block $block): string => $block->getName(), array_values($builder->getBlocks())));
        $this->assertNull($this->blockNamed($builder, self::KEY)->getMaxItems());
        $this->assertTrue($builder->isAddable());
        $this->assertSame(PageBlock::MAX_BLOCKS, $builder->getMaxItems());
    }

    public function test_without_any_block_available_it_says_so_and_nothing_can_be_added(): void
    {
        $this->registerSubscribe(available: false);

        $page = Livewire::test(LinkPageSettingsPage::class)
            ->assertSee(__('link-page::messages.blocks.none_available'));

        $this->assertFalse($this->builderOf($page)->isAddable());
    }

    public function test_with_blocks_available_it_does_not_say_there_are_none(): void
    {
        $this->registerSubscribe(available: true);

        Livewire::test(LinkPageSettingsPage::class)
            ->assertDontSee(__('link-page::messages.blocks.none_available'));
    }

    public function test_it_saves_the_blocks_with_their_titles_and_settings(): void
    {
        $this->registerSubscribe(available: true);

        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm(['blocks' => [
                ['type' => self::KEY, 'data' => ['title' => '  Boletín  ', 'settings' => ['heading' => 'Suscríbete']]],
                ['type' => self::KEY, 'data' => ['title' => '', 'settings' => ['heading' => 'Otra vez']]],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([
            ['key' => self::KEY, 'title' => 'Boletín', 'settings' => ['heading' => 'Suscríbete']],
            ['key' => self::KEY, 'title' => null, 'settings' => ['heading' => 'Otra vez']],
        ], $this->storedBlocks());
    }

    public function test_it_opens_with_the_stored_blocks_and_saving_untouched_keeps_them(): void
    {
        $this->registerSubscribe(available: true);
        $this->store([['key' => self::KEY, 'title' => 'Boletín', 'settings' => ['heading' => 'Suscríbete']]]);

        $page = Livewire::test(LinkPageSettingsPage::class);
        $items = array_values($page->get('data.blocks'));

        $this->assertCount(1, $items);
        $this->assertSame(self::KEY, $items[0]['type']);
        $this->assertSame('Boletín', $items[0]['data']['title']);
        $this->assertSame('Suscríbete', $items[0]['data']['settings']['heading']);

        $page->call('save')->assertHasNoFormErrors();

        $this->assertSame([['key' => self::KEY, 'title' => 'Boletín', 'settings' => ['heading' => 'Suscríbete']]], $this->storedBlocks());
    }

    public function test_removing_every_block_stores_none(): void
    {
        $this->registerSubscribe(available: true);
        $this->store([['key' => self::KEY, 'title' => null, 'settings' => []]]);

        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm(['blocks' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $this->storedBlocks());
    }

    public function test_a_stored_block_that_cannot_be_drawn_now_is_kept_but_cannot_be_added(): void
    {
        $this->registerSubscribe(available: false);
        $this->store([['key' => self::KEY, 'title' => 'Boletín', 'settings' => ['heading' => 'Suscríbete']]]);

        $page = Livewire::test(LinkPageSettingsPage::class)
            ->assertSee(__('link-page::messages.blocks.unavailable'));

        $this->assertSame(0, $this->blockNamed($this->builderOf($page), self::KEY)->getMaxItems());

        $page->call('save')->assertHasNoFormErrors();

        $this->assertSame([['key' => self::KEY, 'title' => 'Boletín', 'settings' => ['heading' => 'Suscríbete']]], $this->storedBlocks());
    }

    public function test_a_stored_block_nobody_registers_keeps_its_title_and_settings(): void
    {
        $this->registerSubscribe(available: true);
        $this->store([
            ['key' => 'gone-module.subscribe', 'title' => 'Boletín', 'settings' => ['heading' => 'Suscríbete', 'list' => 3]],
            ['key' => self::KEY, 'title' => null, 'settings' => ['heading' => 'Hola']],
        ]);

        $page = Livewire::test(LinkPageSettingsPage::class)
            ->assertSee(__('link-page::messages.blocks.unknown.label', ['key' => 'gone-module.subscribe']));

        $this->assertSame(0, $this->blockNamed($this->builderOf($page), 'gone-module.subscribe')->getMaxItems());

        $page->call('save')->assertHasNoFormErrors();

        $this->assertSame([
            ['key' => 'gone-module.subscribe', 'title' => 'Boletín', 'settings' => ['heading' => 'Suscríbete', 'list' => 3]],
            ['key' => self::KEY, 'title' => null, 'settings' => ['heading' => 'Hola']],
        ], $this->storedBlocks());
    }

    public function test_a_block_whose_settings_cannot_be_built_keeps_them_when_saving(): void
    {
        $this->registerClass(new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'example-module.broken';
            }

            public function placements(): array
            {
                return [SiteBlockPlacement::LinkPage];
            }

            public function settingsSchema(): array
            {
                throw new RuntimeException('Sin ajustes');
            }
        });
        $this->store([['key' => 'example-module.broken', 'title' => 'Roto', 'settings' => ['label' => 'Hola']]]);

        Livewire::test(LinkPageSettingsPage::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([['key' => 'example-module.broken', 'title' => 'Roto', 'settings' => ['label' => 'Hola']]], $this->storedBlocks());
    }

    public function test_forged_blocks_and_hidden_copies_are_dropped(): void
    {
        $this->registerSubscribe(available: true);

        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm(['blocks' => [
                ['type' => self::KEY, 'data' => [
                    'title' => null,
                    'settings' => ['heading' => 'Del formulario'],
                    'stored_settings' => '{"heading":"Forjado","extra":"x"}',
                ]],
                // A key nobody registers and that was never stored
                ['type' => 'forged-module.block', 'data' => ['title' => 'Forjado', 'stored_settings' => '{"html":"<script></script>"}']],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([['key' => self::KEY, 'title' => null, 'settings' => ['heading' => 'Del formulario']]], $this->storedBlocks());
    }

    public function test_it_refuses_more_blocks_than_the_maximum(): void
    {
        $this->registerSubscribe(available: true);
        $item = ['type' => self::KEY, 'data' => ['title' => null, 'settings' => ['heading' => 'Hola']]];

        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm(['blocks' => array_fill(0, PageBlock::MAX_BLOCKS + 1, $item)])
            ->call('save')
            ->assertHasFormErrors(['blocks']);

        $this->assertSame([], app(LinkPageSettingsInterface::class)->blocks());
    }

    public function test_it_refuses_a_title_that_is_too_long(): void
    {
        $this->registerSubscribe(available: true);

        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm(['blocks' => [
                ['type' => self::KEY, 'data' => ['title' => str_repeat('a', PageBlock::MAX_TITLE_LENGTH + 1), 'settings' => []]],
            ]])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame([], app(LinkPageSettingsInterface::class)->blocks());
    }

    public function test_the_settings_of_a_block_are_checked_by_the_block(): void
    {
        $this->registerClass(new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'example-module.subscribe';
            }

            public function placements(): array
            {
                return [SiteBlockPlacement::LinkPage];
            }

            public function settingsSchema(): array
            {
                return [TextInput::make('heading')->required()];
            }
        });

        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm(['blocks' => [['type' => self::KEY, 'data' => ['title' => null, 'settings' => ['heading' => '']]]]])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame([], app(LinkPageSettingsInterface::class)->blocks());
    }

    private function registerSubscribe(bool $available): void
    {
        $this->registerClass($available
            ? new class extends FakeSiteBlock
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
            }
            : new class extends FakeSiteBlock
            {
                public function key(): string
                {
                    return 'example-module.subscribe';
                }

                public function placements(): array
                {
                    return [SiteBlockPlacement::LinkPage];
                }

                public function settingsSchema(): array
                {
                    return [TextInput::make('heading')];
                }

                public function isAvailable(): bool
                {
                    return false;
                }
            });
    }

    private function registerClass(FakeSiteBlock $block): void
    {
        app(SiteBlockRegistryInterface::class)->register($block::class, self::OWNER);
    }

    /**
     * Blocks as if the page had saved them before.
     *
     * @param  list<array{key: string, title: string|null, settings: array<string, mixed>}>  $blocks
     */
    private function store(array $blocks): void
    {
        config()->set('modules.settings.link-page.blocks', $blocks);
    }

    /**
     * @return list<array{key: string, title: string|null, settings: array<string, mixed>}>
     */
    private function storedBlocks(): array
    {
        return array_map(
            static fn (PageBlock $block): array => $block->toArray(),
            app(LinkPageSettingsInterface::class)->blocks(),
        );
    }

    /**
     * @param  Testable<LinkPageSettingsPage>  $page
     */
    private function builderOf(Testable $page): Builder
    {
        $builder = $page->instance()->form->getComponent('blocks');
        $this->assertInstanceOf(Builder::class, $builder);

        return $builder;
    }

    private function blockNamed(Builder $builder, string $name): Block
    {
        $block = $builder->getBlock($name);
        $this->assertInstanceOf(Block::class, $block);

        return $block;
    }
}
