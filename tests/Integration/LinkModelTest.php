<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\LinkPage\Domain\Enums\LinkIcon;
use Modules\LinkPage\Domain\Enums\LinkPlacement;
use Modules\LinkPage\Domain\Enums\LinkVisibility;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\LinkModel;
use Modules\LinkPage\Tests\Support\CreatesLinks;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

final class LinkModelTest extends ModuleTestCase
{
    use CreatesLinks;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    public function test_a_new_link_gets_a_uuid_and_the_defaults(): void
    {
        $link = $this->link()->refresh();

        $this->assertTrue(Str::isUuid($link->id));
        $this->assertTrue($link->is_active);
        $this->assertFalse($link->is_featured);
        $this->assertSame(LinkPlacement::Before, $link->placement);
        $this->assertNull($link->icon);
        $this->assertNull($link->description);
        $this->assertNull($link->starts_at);
        $this->assertNull($link->ends_at);
    }

    public function test_the_id_never_changes_when_the_link_is_edited_or_moved(): void
    {
        $link = $this->link();
        $id = $link->id;

        $link->update(['title' => 'Another title', 'url' => 'https://example.test/', 'sort_order' => 9]);

        $this->assertSame($id, $link->refresh()->id);
        $this->assertSame('link:'.$id, $link->linkId());
    }

    public function test_new_links_go_to_the_end_of_the_order(): void
    {
        $first = $this->link(['title' => 'First']);
        $second = $this->link(['title' => 'Second']);
        $third = $this->link(['title' => 'Third']);

        $this->assertGreaterThan($first->sort_order, $second->sort_order);
        $this->assertGreaterThan($second->sort_order, $third->sort_order);
    }

    public function test_the_address_is_stored_normalised(): void
    {
        $link = $this->link(['url' => "  HTTPS://Example.test/a \n"]);

        $this->assertSame('https://Example.test/a', $link->refresh()->url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeAddresses(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript in capitals' => ['JAVASCRIPT:alert(1)'],
            'javascript split by a tab' => ["java\tscript:alert(1)"],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'plain http' => ['http://example.test/'],
            'empty' => [''],
        ];
    }

    #[DataProvider('unsafeAddresses')]
    public function test_an_unsafe_address_is_never_stored(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            $this->link(['url' => $url]);
        } finally {
            $this->assertSame(0, LinkModel::query()->count());
        }
    }

    public function test_an_unsafe_address_is_not_stored_by_an_update_either(): void
    {
        $link = $this->link();

        try {
            $link->update(['url' => 'javascript:alert(1)']);
            $this->fail('The update should have been refused.');
        } catch (InvalidArgumentException) {
            $this->assertSame('https://discord.gg/example', $link->refresh()->url);
        }
    }

    public function test_the_icon_and_placement_come_back_as_enums(): void
    {
        $link = $this->link(['icon' => 'calendar', 'placement' => 'after'])->refresh();

        $this->assertSame(LinkIcon::Calendar, $link->icon);
        $this->assertSame(LinkPlacement::After, $link->placement);
    }

    public function test_it_is_visible_when_active_and_inside_its_dates(): void
    {
        $now = CarbonImmutable::parse('2026-06-16 12:00:00', 'UTC');

        $this->assertSame(LinkVisibility::Visible, $this->link()->visibilityAt($now));
        $this->assertSame(LinkVisibility::Hidden, $this->link(['is_active' => false])->visibilityAt($now));
        $this->assertSame(LinkVisibility::Scheduled, $this->link(['starts_at' => $now->addMinute()])->visibilityAt($now));
        $this->assertSame(LinkVisibility::Visible, $this->link(['starts_at' => $now])->visibilityAt($now));
        $this->assertSame(LinkVisibility::Expired, $this->link(['ends_at' => $now])->visibilityAt($now));
        $this->assertSame(LinkVisibility::Visible, $this->link(['ends_at' => $now->addMinute()])->visibilityAt($now));
    }

    public function test_a_disabled_link_is_hidden_even_inside_its_dates(): void
    {
        $now = CarbonImmutable::parse('2026-06-16 12:00:00', 'UTC');
        $link = $this->link([
            'is_active' => false,
            'starts_at' => $now->subDay(),
            'ends_at' => $now->addDay(),
        ]);

        $this->assertSame(LinkVisibility::Hidden, $link->visibilityAt($now));
    }
}
