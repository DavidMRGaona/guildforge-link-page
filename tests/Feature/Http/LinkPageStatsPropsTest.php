<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The page learns where to send its beacons and whether to send them at all: people who run
 * the panel check the page often, and their visits are not the public's.
 */
final class LinkPageStatsPropsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_visitors_send_their_beacons_to_the_stats_endpoint(): void
    {
        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.endpoint', route('link_page.api.stats'))
                ->where('stats.enabled', true)
            );
    }

    public function test_a_signed_in_member_without_panel_access_still_counts(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->get('/enlaces')
            ->assertInertia(fn (Assert $page) => $page->where('stats.enabled', true));
    }

    public function test_people_with_panel_access_do_not_count(): void
    {
        $this->actingAs(UserModel::factory()->editor()->create())
            ->get('/enlaces')
            ->assertInertia(fn (Assert $page) => $page->where('stats.enabled', false));
    }

    public function test_loading_the_page_counts_nothing_by_itself(): void
    {
        // The visit is the page's beacon, so a reload or a crawler that runs no script counts nothing
        $this->get('/enlaces')->assertOk();

        $this->assertSame(0, DB::table('link_page_stats')->count());
    }
}
