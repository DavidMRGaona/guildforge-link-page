<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Application;

use Modules\LinkPage\Application\Contracts\ClockInterface;
use Modules\LinkPage\Application\Services\NoticeService;
use Modules\LinkPage\Tests\Support\FixedClock;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The notice shows only inside its window, judged by the injected clock: the edges are the
 * exact second it starts and the exact second it ends, whatever the display zone.
 */
final class NoticeServiceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    private FixedClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FixedClock('2026-11-10T08:59:59Z');
        $this->app->instance(ClockInterface::class, $this->clock);
    }

    public function test_there_is_no_notice_when_none_is_configured(): void
    {
        $this->assertNull($this->service()->current());
    }

    public function test_a_notice_without_dates_always_shows(): void
    {
        $this->configure([]);

        $this->assertSame('Closed on Saturday.', $this->service()->current()?->text);
    }

    public function test_it_appears_on_the_second_it_starts_and_goes_on_the_second_it_ends(): void
    {
        // 10:00 to 12:00 in Madrid in November (UTC+1)
        $this->configure(['starts_at' => '2026-11-10T10:00:00+01:00', 'ends_at' => '2026-11-10T12:00:00+01:00']);

        $this->clock->set('2026-11-10T08:59:59Z');
        $this->assertNull($this->service()->current());

        $this->clock->set('2026-11-10T09:00:00Z');
        $this->assertNotNull($this->service()->current());

        $this->clock->set('2026-11-10T10:59:59Z');
        $this->assertNotNull($this->service()->current());

        $this->clock->set('2026-11-10T11:00:00Z');
        $this->assertNull($this->service()->current());
    }

    public function test_the_window_follows_the_clock_across_a_daylight_saving_change(): void
    {
        // Madrid goes back an hour on 2026-10-25 at 03:00 (01:00 UTC): 02:30 happens twice.
        // 2026-10-25T02:30+02:00 is 00:30 UTC; the notice lasts one real hour from there.
        $this->configure(['starts_at' => '2026-10-25T02:30:00+02:00', 'ends_at' => '2026-10-25T02:30:00+01:00']);

        $this->clock->set('2026-10-25T00:29:59Z');
        $this->assertNull($this->service()->current());

        $this->clock->set('2026-10-25T00:30:00Z');
        $this->assertNotNull($this->service()->current());

        // Wall clocks read 02:30 again, but it is the end: 01:30 UTC
        $this->clock->set('2026-10-25T01:29:59Z');
        $this->assertNotNull($this->service()->current());

        $this->clock->set('2026-10-25T01:30:00Z');
        $this->assertNull($this->service()->current());
    }

    public function test_a_disabled_notice_never_shows(): void
    {
        $this->configure(['enabled' => false]);

        $this->assertNull($this->service()->current());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configure(array $overrides): void
    {
        config()->set('modules.settings.link-page.notice', array_replace([
            'enabled' => true,
            'text' => 'Closed on Saturday.',
            'tone' => 'warning',
        ], $overrides));
    }

    private function service(): NoticeService
    {
        return $this->app->make(NoticeService::class);
    }
}
