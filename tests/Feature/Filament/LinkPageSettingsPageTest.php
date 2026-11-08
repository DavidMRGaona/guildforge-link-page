<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament;

use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Domain\Enums\CalendarView;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Modules\LinkPage\LinkPageServiceProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The page settings open with link-page:manage (admins hold every permission), not with the
 * core's modules.configure.
 */
final class LinkPageSettingsPageTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const string MANAGE = 'link-page:manage';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));
        $this->registerOnAdminPanel([], [LinkPageSettingsPage::class]);
    }

    public function test_the_provider_registers_the_page_and_the_permission(): void
    {
        $provider = new LinkPageServiceProvider(app());

        $this->assertContains(LinkPageSettingsPage::class, $provider->registerFilamentPages());
        $this->assertContains('manage', array_map(fn ($permission): string => $permission->name, $provider->registerPermissions()));
    }

    public function test_only_page_managers_open_the_page(): void
    {
        $this->actingAs($this->userWith([]));
        $this->assertFalse(LinkPageSettingsPage::canAccess());
        Livewire::test(LinkPageSettingsPage::class)->assertForbidden();

        $this->actingAs($this->userWith([self::MANAGE]));
        $this->assertTrue(LinkPageSettingsPage::canAccess());
        Livewire::test(LinkPageSettingsPage::class)->assertOk();
    }

    public function test_the_form_opens_with_the_release_defaults(): void
    {
        $this->actingAsPanelAdmin();

        Livewire::test(LinkPageSettingsPage::class)
            ->assertFormSet([
                'enabled' => true,
                'events.limit' => 5,
                'events.within_days' => 60,
                'calendar.initial_view' => 'list',
            ]);
    }

    public function test_it_saves_the_settings(): void
    {
        $this->actingAsPanelAdmin();
        $tag = TagModel::factory()->forEvents()->create(['slug' => 'wargames']);
        $pinned = $this->event('Pinned');
        $hidden = $this->event('Hidden');

        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm([
                'enabled' => false,
                'intro' => 'Welcome to the guild.',
                'events.limit' => 3,
                'events.within_days' => 14,
                'events.tags' => [$tag->slug],
                'events.pinned' => [$pinned->id],
                'events.hidden' => [$hidden->id],
                'calendar.initial_view' => 'month',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(LinkPageSettingsInterface::class);
        $this->assertFalse($settings->isEnabled());
        $this->assertSame('Welcome to the guild.', $settings->intro());
        $this->assertSame(3, $settings->eventsLimit());
        $this->assertSame(14, $settings->eventsWithinDays());
        $this->assertSame(['wargames'], $settings->eventTagSlugs());
        $this->assertSame([$pinned->id], $settings->pinnedEventIds());
        $this->assertSame([$hidden->id], $settings->hiddenEventIds());
        $this->assertSame(CalendarView::Month, $settings->calendarInitialView());
    }

    public function test_values_outside_the_options_are_refused(): void
    {
        $this->actingAsPanelAdmin();
        $draft = $this->event('Draft', published: false);

        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm([
                'events.limit' => 0,
                'events.within_days' => LinkPageSettingsInterface::MAX_WITHIN_DAYS + 1,
                'events.tags' => ['not-a-tag'],
                'events.pinned' => [$draft->id],
                'events.hidden' => ['not-an-event'],
                'calendar.initial_view' => 'week',
            ])
            ->call('save')
            ->assertHasFormErrors([
                'events.limit',
                'events.within_days',
                'events.tags',
                'events.pinned',
                'events.hidden',
                'calendar.initial_view',
            ]);
    }

    public function test_saving_keeps_the_keys_the_form_does_not_show(): void
    {
        $this->actingAsPanelAdmin();
        app(ModuleSettingsStoreInterface::class)->store('link-page', ['future_feature' => ['enabled' => true]]);

        Livewire::test(LinkPageSettingsPage::class)->call('save')->assertHasNoFormErrors();

        $this->assertSame(['enabled' => true], app(ModuleSettingsStoreInterface::class)->stored('link-page')['future_feature'] ?? null);
    }

    public function test_saving_is_refused_once_the_permission_is_gone(): void
    {
        $this->actingAs($this->userWith([self::MANAGE]));
        $page = Livewire::test(LinkPageSettingsPage::class)->fillForm(['enabled' => false]);

        $this->actingAs($this->userWith([]));
        $page->call('save')->assertForbidden();

        $this->assertTrue(app(LinkPageSettingsInterface::class)->isEnabled());
    }

    private function event(string $title, bool $published = true): EventModel
    {
        return EventModel::factory()->create([
            'title' => $title,
            'start_date' => CarbonImmutable::parse('2026-06-20 18:00:00', 'UTC'),
            'end_date' => CarbonImmutable::parse('2026-06-20 22:00:00', 'UTC'),
            'is_published' => $published,
        ]);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function userWith(array $permissionKeys): UserModel
    {
        $role = RoleModel::query()->create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'link-page', 'action' => 'manage', 'module' => 'link-page'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
