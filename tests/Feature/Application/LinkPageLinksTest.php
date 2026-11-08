<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Application;

use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\LinkPage\Application\Contracts\LinkPageLinksInterface;
use Modules\LinkPage\Application\DTOs\LinkPageLinkDTO;
use Modules\LinkPage\Domain\Enums\LinkIcon;
use Modules\LinkPage\Domain\Enums\LinkPlacement;
use Modules\LinkPage\Tests\Support\CreatesLinks;
use Tests\Support\Modules\ModuleTestCase;

final class LinkPageLinksTest extends ModuleTestCase
{
    use CreatesLinks;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->travelTo(CarbonImmutable::parse('2026-06-16 12:00:00', 'UTC'));
    }

    public function test_no_links_gives_an_empty_list(): void
    {
        $this->assertSame([], $this->links()->visible());
    }

    public function test_links_come_in_the_dragged_order_with_everything_the_page_needs(): void
    {
        $this->link(['title' => 'Second', 'sort_order' => 20]);
        $this->link([
            'title' => 'First',
            'url' => 'https://example.test/form',
            'description' => 'Join the association',
            'icon' => 'form',
            'is_featured' => true,
            'placement' => 'after',
            'sort_order' => 10,
        ]);

        $links = $this->links()->visible();

        $this->assertSame(['First', 'Second'], array_map(fn (LinkPageLinkDTO $link): string => $link->title, $links));

        $first = $links[0];
        $this->assertSame('https://example.test/form', $first->url->value);
        $this->assertSame('Join the association', $first->description);
        $this->assertSame(LinkIcon::Form, $first->icon);
        $this->assertTrue($first->featured);
        $this->assertSame(LinkPlacement::After, $first->placement);
        $this->assertSame('link:'.$first->id, $first->linkId());
    }

    public function test_a_switched_off_link_is_not_shown(): void
    {
        $this->link(['title' => 'On']);
        $this->link(['title' => 'Off', 'is_active' => false]);

        $this->assertSame(['On'], $this->titles());
    }

    public function test_a_link_waits_for_its_start_and_goes_at_its_end_in_the_display_zone(): void
    {
        config()->set('app.display_timezone', 'Europe/Madrid');
        $timezone = app(TimezoneServiceInterface::class);

        // 20:00 in Madrid in June is 18:00 UTC; the end is 22:00 in Madrid, 20:00 UTC
        $this->link([
            'title' => 'Evening only',
            'starts_at' => $timezone->fromDisplayWallTime('2026-06-16 20:00'),
            'ends_at' => $timezone->fromDisplayWallTime('2026-06-16 22:00'),
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-06-16 17:59:59', 'UTC'));
        $this->assertSame([], $this->titles(), 'before the start');

        $this->travelTo(CarbonImmutable::parse('2026-06-16 18:00:00', 'UTC'));
        $this->assertSame(['Evening only'], $this->titles(), 'at the start');

        $this->travelTo(CarbonImmutable::parse('2026-06-16 19:59:59', 'UTC'));
        $this->assertSame(['Evening only'], $this->titles(), 'just before the end');

        $this->travelTo(CarbonImmutable::parse('2026-06-16 20:00:00', 'UTC'));
        $this->assertSame([], $this->titles(), 'at the end');
    }

    public function test_a_stored_address_that_is_not_valid_never_reaches_the_page(): void
    {
        $this->link(['title' => 'Fine']);
        $unsafe = $this->link(['title' => 'Tampered']);
        // Written behind the model's back, as an old import or a manual edit could
        DB::table('link_page_links')->where('id', $unsafe->id)->update(['url' => 'javascript:alert(1)']);

        $this->assertSame(['Fine'], $this->titles());
    }

    /**
     * @return list<string>
     */
    private function titles(): array
    {
        return array_map(fn (LinkPageLinkDTO $link): string => $link->title, $this->links()->visible());
    }

    private function links(): LinkPageLinksInterface
    {
        return app(LinkPageLinksInterface::class);
    }
}
