<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use Modules\LinkPage\Domain\ValueObjects\PageBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The site blocks stored for the link page (link-page#48), read tolerantly: anything malformed is
 * no block, never an error.
 */
final class PageBlockTest extends TestCase
{
    public function test_it_reads_the_stored_blocks_in_order(): void
    {
        $blocks = PageBlock::listFrom([
            ['key' => 'example-module.subscribe', 'title' => 'Boletín', 'settings' => ['heading' => 'Suscríbete']],
            ['key' => 'other-module.block', 'title' => null, 'settings' => []],
        ]);

        $this->assertCount(2, $blocks);
        $this->assertSame(['key' => 'example-module.subscribe', 'title' => 'Boletín', 'settings' => ['heading' => 'Suscríbete']], $blocks[0]->toArray());
        $this->assertSame(['key' => 'other-module.block', 'title' => null, 'settings' => []], $blocks[1]->toArray());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notAList(): iterable
    {
        yield 'nothing' => [null];
        yield 'a string' => ['example-module.subscribe'];
        yield 'a number' => [3];
    }

    #[DataProvider('notAList')]
    public function test_anything_but_a_list_is_no_block(mixed $stored): void
    {
        $this->assertSame([], PageBlock::listFrom($stored));
    }

    public function test_entries_without_a_usable_key_are_left_out(): void
    {
        $blocks = PageBlock::listFrom([
            'not an entry',
            ['title' => 'Sin clave'],
            ['key' => ''],
            ['key' => '   '],
            ['key' => 42],
            ['key' => str_repeat('a', PageBlock::MAX_KEY_LENGTH + 1)],
            ['key' => 'example-module.subscribe'],
        ]);

        $this->assertCount(1, $blocks);
        $this->assertSame('example-module.subscribe', $blocks[0]->key);
        $this->assertNull($blocks[0]->title);
        $this->assertSame([], $blocks[0]->settings);
    }

    public function test_the_title_is_trimmed_cut_and_blank_means_none(): void
    {
        $titles = array_map(
            static fn (mixed $title): ?string => PageBlock::listFrom([['key' => 'a-module.a', 'title' => $title]])[0]->title,
            ['  Boletín  ', '   ', ['no'], str_repeat('é', PageBlock::MAX_TITLE_LENGTH + 10)],
        );

        $this->assertSame(['Boletín', null, null, str_repeat('é', PageBlock::MAX_TITLE_LENGTH)], $titles);
    }

    public function test_settings_keep_only_their_named_values(): void
    {
        $blocks = PageBlock::listFrom([
            ['key' => 'a-module.a', 'settings' => 'not settings'],
            ['key' => 'a-module.b', 'settings' => ['heading' => 'Hola', 0 => 'sin nombre', 'list' => 3]],
        ]);

        $this->assertSame([], $blocks[0]->settings);
        $this->assertSame(['heading' => 'Hola', 'list' => 3], $blocks[1]->settings);
    }

    public function test_it_keeps_at_most_the_maximum_number_of_blocks(): void
    {
        $stored = [];
        for ($i = 0; $i < PageBlock::MAX_BLOCKS + 2; $i++) {
            $stored[] = ['key' => 'a-module.block'.$i];
        }

        $blocks = PageBlock::listFrom($stored);

        $this->assertCount(PageBlock::MAX_BLOCKS, $blocks);
        $this->assertSame('a-module.block0', $blocks[0]->key);
    }
}
