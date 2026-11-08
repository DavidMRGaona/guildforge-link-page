<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\PersonalCalendarServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Mi calendario» on /enlaces: signed-in people get a way to their own calendar subscription,
 * and only while the core has a personal calendar to subscribe to. The page never builds the
 * feed address: only its hash is stored, so the profile (where it is issued) is the way in.
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

    public function test_guests_only_get_the_public_feed(): void
    {
        $this->personalCalendarIsAvailable(true);

        $this->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('personalCalendar', null)
                ->has('calendarFeed.feedUrl')
            );
    }

    public function test_a_signed_in_person_is_taken_to_the_calendar_tab_of_their_profile(): void
    {
        $this->personalCalendarIsAvailable(true);

        $this->actingAs(UserModel::factory()->create())
            ->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('personalCalendar.profileUrl', route('profile.show', ['tab' => 'calendar']))
            );
    }

    public function test_it_is_hidden_while_the_core_has_no_personal_calendar(): void
    {
        $this->personalCalendarIsAvailable(false);

        $this->actingAs(UserModel::factory()->create())
            ->get('/enlaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('personalCalendar', null));
    }

    public function test_the_page_never_carries_a_personal_feed_address(): void
    {
        $this->personalCalendarIsAvailable(true);

        $response = $this->actingAs(UserModel::factory()->create())->get('/enlaces');

        $this->assertStringNotContainsString('/calendario/personal/', (string) $response->getContent());
    }

    private function personalCalendarIsAvailable(bool $available): void
    {
        $this->app->instance(PersonalCalendarServiceInterface::class, new readonly class($available) implements PersonalCalendarServiceInterface
        {
            public function __construct(private bool $available) {}

            public function isAvailable(): bool
            {
                return $this->available;
            }

            /** @return array<CalendarEntryDTO> */
            public function entriesFor(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
            {
                return [];
            }
        });
    }
}
