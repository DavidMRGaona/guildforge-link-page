<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Modules\ModuleTestCase;

/**
 * /enlaces is for people arriving from outside: it offers the public feed only. Signed-in
 * people reach their own calendar from the site, so the page has no way in to it.
 */
final class LinkPagePersonalCalendarTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_get_the_public_feed(): void
    {
        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('calendarFeed.feedUrl')
                ->missing('personalCalendar')
            );
    }

    public function test_a_signed_in_person_gets_the_public_feed_and_nothing_of_their_own(): void
    {
        $response = $this->actingAs(UserModel::factory()->create())->get('/enlaces');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('calendarFeed.feedUrl')
                ->missing('personalCalendar')
            );
        $this->assertStringNotContainsString('/calendario/personal/', (string) $response->getContent());
    }
}
