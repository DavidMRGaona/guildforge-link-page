<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Modules\LinkPage\Infrastructure\Stats\TrafficFilter;
use Modules\LinkPage\Tests\Support\CreatesLinks;
use Tests\Support\Modules\ModuleTestCase;

/**
 * POST /api/link-page/stats: what navigator.sendBeacon sends from the page. It counts only
 * what the page shows, never stores anything about the person and never sets a cookie.
 */
final class StatsBeaconTest extends ModuleTestCase
{
    use CreatesLinks;

    private const string PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1';

    private const string ADDRESS = '203.0.113.7';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));
    }

    public function test_a_visit_counts_and_answers_without_content(): void
    {
        $this->beacon(['type' => 'visit'])->assertNoContent();

        $this->assertSame(1, $this->storedCount('visit', ''));
    }

    public function test_a_crawler_gets_the_same_answer_without_counting(): void
    {
        $this->beacon(['type' => 'visit'], userAgent: 'Mozilla/5.0 (compatible; Googlebot/2.1)')->assertNoContent();

        $this->assertSame(0, $this->storedCount('visit', ''));
    }

    public function test_a_click_on_an_own_link_the_page_shows_counts(): void
    {
        $link = $this->link();

        $this->beacon(['type' => 'click', 'id' => $link->linkId()])->assertNoContent();

        $this->assertSame(1, $this->storedCount('click', $link->linkId()));
    }

    public function test_a_link_switched_off_or_unknown_is_refused(): void
    {
        $off = $this->link(['is_active' => false]);

        $this->beacon(['type' => 'click', 'id' => $off->linkId()])->assertUnprocessable();
        $this->beacon(['type' => 'click', 'id' => 'link:0199f3a2-7b1c-7d4e-9a10-3c5b2e8f6d41'])->assertUnprocessable();
        $this->beacon(['type' => 'click', 'id' => 'https://evil.example'])->assertUnprocessable();
        $this->beacon(['type' => 'click'])->assertUnprocessable();
        $this->beacon(['type' => 'download'])->assertUnprocessable();

        $this->assertSame(0, (int) DB::table('link_page_stats')->count());
    }

    public function test_a_click_on_a_social_icon_counts_only_for_a_network_the_page_shows(): void
    {
        app(SettingsServiceInterface::class)->set('social_discord', 'discord.gg/example');

        $this->beacon(['type' => 'click', 'id' => 'social:discord'])->assertNoContent();
        $this->beacon(['type' => 'click', 'id' => 'social:instagram'])->assertUnprocessable();

        $this->assertSame(1, $this->storedCount('click', 'social:discord'));
    }

    public function test_a_click_on_an_event_button_counts_only_for_a_listed_event(): void
    {
        $listed = $this->event('Campaign night', '2026-06-16 18:00:00');
        $draft = EventModel::factory()->create([
            'title' => 'Draft night',
            'is_published' => false,
            'start_date' => CarbonImmutable::parse('2026-06-17 18:00:00', 'UTC'),
            'end_date' => CarbonImmutable::parse('2026-06-17 23:00:00', 'UTC'),
        ]);

        $this->beacon(['type' => 'click', 'id' => 'event:'.$listed->id])->assertNoContent();
        $this->beacon(['type' => 'click', 'id' => 'event:'.$draft->id])->assertUnprocessable();

        $this->assertSame(1, $this->storedCount('click', 'event:'.$listed->id));
    }

    public function test_the_featured_event_counts_apart_and_only_while_it_is_featured(): void
    {
        $featured = $this->event('Spring tournament', '2026-06-16 18:00:00');
        $other = $this->event('Board games', '2026-06-18 18:00:00');
        config()->set('modules.settings.link-page.featured', ['mode' => 'auto']);

        $this->beacon(['type' => 'click', 'id' => 'featured:'.$featured->id])->assertNoContent();
        $this->beacon(['type' => 'click', 'id' => 'featured:'.$other->id])->assertUnprocessable();
        // Out of the list while it has its own block
        $this->beacon(['type' => 'click', 'id' => 'event:'.$featured->id])->assertUnprocessable();
        $this->beacon(['type' => 'click', 'id' => 'event:'.$other->id])->assertNoContent();

        $this->assertSame(1, $this->storedCount('click', 'featured:'.$featured->id));
        $this->assertSame(1, $this->storedCount('click', 'event:'.$other->id));
    }

    public function test_the_notice_link_counts_only_while_a_notice_with_a_link_is_shown(): void
    {
        $this->beacon(['type' => 'click', 'id' => 'notice'])->assertUnprocessable();

        config()->set('modules.settings.link-page.notice', ['enabled' => true, 'text' => 'Closed on Saturday.', 'tone' => 'info']);
        $this->beacon(['type' => 'click', 'id' => 'notice'])->assertUnprocessable();

        config()->set('modules.settings.link-page.notice', [
            'enabled' => true,
            'text' => 'Closed on Saturday.',
            'tone' => 'info',
            'link_url' => '/eventos',
        ]);
        $this->beacon(['type' => 'click', 'id' => 'notice'])->assertNoContent();

        $this->assertSame(1, $this->storedCount('click', 'notice'));
    }

    public function test_a_click_on_the_site_link_counts(): void
    {
        $this->beacon(['type' => 'click', 'id' => 'home'])->assertNoContent();

        $this->assertSame(1, $this->storedCount('click', 'home'));
    }

    public function test_an_entry_of_the_calendar_counts_by_the_address_it_opens(): void
    {
        $event = $this->event('Board games', '2026-07-20 18:00:00');
        $url = route('events.show', $event->slug);

        $this->beacon(['type' => 'click', 'id' => 'calendar', 'url' => $url])->assertNoContent();

        $this->assertSame(1, $this->storedCount('click', 'calendar:event:'.$event->id));
    }

    public function test_an_address_that_is_no_calendar_entry_or_of_another_site_is_refused(): void
    {
        $event = $this->event('Board games', '2026-07-20 18:00:00');

        $this->beacon(['type' => 'click', 'id' => 'calendar', 'url' => 'https://evil.example/eventos/'.$event->slug])->assertUnprocessable();
        $this->beacon(['type' => 'click', 'id' => 'calendar', 'url' => url('/eventos/unknown')])->assertUnprocessable();
        $this->beacon(['type' => 'click', 'id' => 'calendar'])->assertUnprocessable();

        $this->assertSame(0, (int) DB::table('link_page_stats')->count());
    }

    public function test_a_double_tap_on_the_same_link_counts_once(): void
    {
        $link = $this->link();

        $this->beacon(['type' => 'click', 'id' => $link->linkId()]);
        $this->beacon(['type' => 'click', 'id' => $link->linkId()]);
        $this->assertSame(1, $this->storedCount('click', $link->linkId()));

        $this->travel(TrafficFilter::REPEAT_WINDOW['click'] + 1)->seconds();
        $this->beacon(['type' => 'click', 'id' => $link->linkId()]);
        $this->assertSame(2, $this->storedCount('click', $link->linkId()));
    }

    public function test_the_endpoint_is_rate_limited_per_device(): void
    {
        for ($i = 0; $i < TrafficFilter::BEACONS_PER_MINUTE; $i++) {
            $this->beacon(['type' => 'visit'])->assertNoContent();
        }

        $this->beacon(['type' => 'visit'])->assertTooManyRequests();
        $this->beacon(['type' => 'visit'], userAgent: self::PHONE.' other')->assertNoContent();
    }

    public function test_changing_the_agent_on_every_request_does_not_get_round_the_limit(): void
    {
        for ($i = 0; $i < TrafficFilter::BEACONS_PER_ADDRESS_MINUTE; $i++) {
            $this->beacon(['type' => 'visit'], userAgent: self::PHONE.' '.$i)->assertNoContent();
        }

        $this->beacon(['type' => 'visit'], userAgent: self::PHONE.' one more')->assertTooManyRequests();
    }

    public function test_nothing_answers_while_the_page_is_off(): void
    {
        config()->set('modules.settings.link-page.enabled', false);

        $this->beacon(['type' => 'visit'])->assertNotFound();
    }

    public function test_nothing_that_identifies_the_person_is_stored_or_set(): void
    {
        $user = UserModel::factory()->create(['email' => 'visitor@example.test']);
        $link = $this->link();

        $responses = [
            $this->actingAs($user)->withCookie('tracking', 'cookie-value-123')->beacon(['type' => 'visit']),
            $this->beacon(['type' => 'click', 'id' => $link->linkId()]),
        ];

        foreach ($responses as $response) {
            $response->assertNoContent();
            $this->assertSame([], $response->headers->getCookies());
        }

        $stored = json_encode(DB::table('link_page_stats')->get()->all(), JSON_THROW_ON_ERROR);
        foreach ([self::ADDRESS, 'iPhone', 'Mozilla', 'cookie-value-123', 'visitor@example.test', $user->id, TrafficFilter::device(request())] as $identifying) {
            $this->assertStringNotContainsString((string) $identifying, $stored);
        }
        $this->assertSame(2, (int) DB::table('link_page_stats')->sum('count'));
    }

    /**
     * @param  array<string, string>  $body
     * @return TestResponse<Response>
     */
    private function beacon(array $body, string $userAgent = self::PHONE): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::ADDRESS])
            ->withHeader('User-Agent', $userAgent)
            ->post('/api/link-page/stats', $body);
    }

    private function storedCount(string $kind, string $subject): int
    {
        return (int) DB::table('link_page_stats')->where('kind', $kind)->where('subject', $subject)->sum('count');
    }

    private function event(string $title, string $startsAt): EventModel
    {
        return EventModel::factory()->published()->create([
            'title' => $title,
            'start_date' => CarbonImmutable::parse($startsAt, 'UTC'),
            'end_date' => CarbonImmutable::parse($startsAt, 'UTC')->addHours(5),
        ]);
    }
}
