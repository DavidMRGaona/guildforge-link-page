<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Application;

use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Domain\Enums\CalendarView;
use Tests\Support\Modules\ModuleTestCase;

final class LinkPageSettingsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    public function test_release_defaults(): void
    {
        $settings = $this->settings();

        $this->assertTrue($settings->isEnabled());
        $this->assertNull($settings->intro());
        $this->assertSame(5, $settings->eventsLimit());
        $this->assertSame(60, $settings->eventsWithinDays());
        $this->assertSame([], $settings->eventTagSlugs());
        $this->assertSame([], $settings->pinnedEventIds());
        $this->assertSame([], $settings->hiddenEventIds());
        $this->assertSame(CalendarView::List, $settings->calendarInitialView());
    }

    public function test_saved_values_win(): void
    {
        config()->set('modules.settings.link-page', [
            'enabled' => false,
            'intro' => '  Hello there  ',
            'events' => [
                'limit' => '8',
                'within_days' => '30',
                'tags' => ['wargames', ' rol ', '', 'wargames'],
                'pinned' => ['a', 'b', 'a'],
                'hidden' => ['c'],
            ],
            'calendar' => ['initial_view' => 'month'],
        ]);

        $settings = $this->settings();

        $this->assertFalse($settings->isEnabled());
        $this->assertSame('Hello there', $settings->intro());
        $this->assertSame(8, $settings->eventsLimit());
        $this->assertSame(30, $settings->eventsWithinDays());
        $this->assertSame(['wargames', 'rol'], $settings->eventTagSlugs());
        $this->assertSame(['a', 'b'], $settings->pinnedEventIds());
        $this->assertSame(['c'], $settings->hiddenEventIds());
        $this->assertSame(CalendarView::Month, $settings->calendarInitialView());
    }

    public function test_values_out_of_range_or_malformed_fall_back(): void
    {
        config()->set('modules.settings.link-page', [
            'intro' => '   ',
            'events' => [
                'limit' => '0',
                'within_days' => 'soon',
                'tags' => 'wargames',
                'pinned' => [['nested'], 7, 'ok'],
                'hidden' => null,
            ],
            'calendar' => ['initial_view' => 'week'],
        ]);

        $settings = $this->settings();

        $this->assertNull($settings->intro());
        $this->assertSame(5, $settings->eventsLimit());
        $this->assertSame(60, $settings->eventsWithinDays());
        $this->assertSame([], $settings->eventTagSlugs());
        $this->assertSame(['ok'], $settings->pinnedEventIds());
        $this->assertSame([], $settings->hiddenEventIds());
        $this->assertSame(CalendarView::List, $settings->calendarInitialView());
    }

    public function test_limits_are_capped(): void
    {
        config()->set('modules.settings.link-page.events', ['limit' => 500, 'within_days' => 5000]);

        $settings = $this->settings();

        $this->assertSame(LinkPageSettingsInterface::MAX_EVENTS, $settings->eventsLimit());
        $this->assertSame(LinkPageSettingsInterface::MAX_WITHIN_DAYS, $settings->eventsWithinDays());
    }

    private function settings(): LinkPageSettingsInterface
    {
        return $this->app->make(LinkPageSettingsInterface::class);
    }
}
