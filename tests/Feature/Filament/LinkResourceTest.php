<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament;

use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Services\TimezoneServiceInterface;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Livewire;
use Modules\LinkPage\Domain\Enums\LinkIcon;
use Modules\LinkPage\Domain\Enums\LinkPlacement;
use Modules\LinkPage\Filament\Resources\LinkResource;
use Modules\LinkPage\Filament\Resources\LinkResource\Pages\CreateLink;
use Modules\LinkPage\Filament\Resources\LinkResource\Pages\EditLink;
use Modules\LinkPage\Filament\Resources\LinkResource\Pages\ListLinks;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\LinkModel;
use Modules\LinkPage\LinkPageServiceProvider;
use Modules\LinkPage\Tests\Support\CreatesLinks;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class LinkResourceTest extends ModuleTestCase
{
    use CreatesLinks;
    use InteractsWithModulePanel;

    private const string MANAGE = 'link-page:links.manage';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([LinkResource::class]);
    }

    public function test_the_provider_registers_the_resource_the_policy_and_the_permission(): void
    {
        $provider = new LinkPageServiceProvider(app());
        $permissions = array_map(fn (PermissionDTO $permission): string => $permission->name, $provider->registerPermissions());

        $this->assertContains(LinkResource::class, $provider->registerFilamentResources());
        $this->assertArrayHasKey(LinkModel::class, $provider->registerPolicies());
        $this->assertContains('links.manage', $permissions);
    }

    public function test_only_link_managers_open_the_list(): void
    {
        $this->actingAs($this->userWithLinkPermissions(['link-page:settings.manage']));
        $this->assertFalse(LinkResource::canViewAny());
        Livewire::test(ListLinks::class)->assertForbidden();

        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));
        $this->assertTrue(LinkResource::canViewAny());
        Livewire::test(ListLinks::class)->assertOk();
    }

    public function test_it_creates_a_link_with_everything_the_form_asks(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));

        Livewire::test(CreateLink::class)
            ->fillForm([
                'title' => 'Join our Discord',
                'url' => 'https://discord.gg/example',
                'description' => 'Chat with the guild',
                'icon' => 'discord',
                'placement' => 'after',
                'is_featured' => true,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $link = LinkModel::query()->sole();
        $this->assertSame('Join our Discord', $link->title);
        $this->assertSame('https://discord.gg/example', $link->url);
        $this->assertSame('Chat with the guild', $link->description);
        $this->assertSame(LinkIcon::Discord, $link->icon);
        $this->assertSame(LinkPlacement::After, $link->placement);
        $this->assertTrue($link->is_featured);
    }

    public function test_the_address_is_saved_trimmed_with_the_scheme_in_lower_case(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));

        Livewire::test(CreateLink::class)
            ->fillForm(['title' => 'Form', 'url' => '  HTTPS://Example.test/join  '])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('https://Example.test/join', LinkModel::query()->sole()->url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeAddresses(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript in capitals' => ['JAVASCRIPT:alert(1)'],
            'javascript with a leading space' => [' javascript:alert(1)'],
            'javascript split by a tab' => ["java\tscript:alert(1)"],
            'data' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            'vbscript' => ['vbscript:msgbox(1)'],
            'plain http' => ['http://example.test/'],
            'no scheme' => ['example.test'],
            'file' => ['file:///etc/passwd'],
        ];
    }

    #[DataProvider('unsafeAddresses')]
    public function test_an_unsafe_address_is_refused_by_the_server(string $url): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));

        Livewire::test(CreateLink::class)
            ->fillForm(['title' => 'Sneaky', 'url' => $url])
            ->call('create')
            ->assertHasFormErrors(['url']);

        $this->assertSame(0, LinkModel::query()->count());
    }

    #[DataProvider('unsafeAddresses')]
    public function test_an_unsafe_address_is_refused_when_editing_too(string $url): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));
        $link = $this->link();

        Livewire::test(EditLink::class, ['record' => $link->getRouteKey()])
            ->fillForm(['url' => $url])
            ->call('save')
            ->assertHasFormErrors(['url']);

        $this->assertSame('https://discord.gg/example', $link->refresh()->url);
    }

    public function test_mail_and_phone_addresses_are_accepted(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));

        Livewire::test(CreateLink::class)
            ->fillForm(['title' => 'Write to us', 'url' => 'mailto:info@example.test'])
            ->call('create')
            ->assertHasNoFormErrors();
        Livewire::test(CreateLink::class)
            ->fillForm(['title' => 'Call us', 'url' => 'tel:+34 600 123 456'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            ['mailto:info@example.test', 'tel:+34600123456'],
            LinkModel::query()->orderBy('sort_order')->pluck('url')->all(),
        );
    }

    public function test_title_and_address_are_required_and_bounded(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));

        Livewire::test(CreateLink::class)
            ->fillForm(['title' => '', 'url' => ''])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required', 'url' => 'required']);

        Livewire::test(CreateLink::class)
            ->fillForm([
                'title' => str_repeat('t', LinkModel::MAX_TITLE_LENGTH + 1),
                'url' => 'https://example.test/',
                'description' => str_repeat('d', LinkModel::MAX_DESCRIPTION_LENGTH + 1),
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'max', 'description' => 'max']);
    }

    public function test_the_icon_and_placement_come_from_closed_lists(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));

        Livewire::test(CreateLink::class)
            ->fillForm(['title' => 'X', 'url' => 'https://example.test/', 'icon' => 'myspace', 'placement' => 'sideways'])
            ->call('create')
            ->assertHasFormErrors(['icon', 'placement']);
    }

    public function test_the_dates_are_typed_in_the_display_zone_and_stored_as_instants(): void
    {
        config()->set('app.display_timezone', 'Europe/Madrid');
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));

        Livewire::test(CreateLink::class)
            ->fillForm([
                'title' => 'Evening only',
                'url' => 'https://example.test/',
                'starts_at' => '2026-06-16 20:00:00',
                'ends_at' => '2026-06-16 22:00:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $link = LinkModel::query()->sole();
        $timezone = app(TimezoneServiceInterface::class);

        // 20:00 and 22:00 in Madrid in June are 18:00 and 20:00 UTC, whatever zone the table stores in
        $this->assertSame('2026-06-16 18:00', $link->starts_at?->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-06-16 20:00', $link->ends_at?->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-06-16 20:00', $timezone->format($link->starts_at ?? $this->fail(), 'Y-m-d H:i'));
    }

    public function test_the_end_has_to_come_after_the_start(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));

        Livewire::test(CreateLink::class)
            ->fillForm([
                'title' => 'Backwards',
                'url' => 'https://example.test/',
                'starts_at' => '2026-06-16 22:00:00',
                'ends_at' => '2026-06-16 20:00:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['ends_at']);
    }

    public function test_it_edits_a_link_without_changing_its_id(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));
        $link = $this->link();
        $id = $link->id;

        Livewire::test(EditLink::class, ['record' => $link->getRouteKey()])
            ->fillForm(['title' => 'Renamed', 'is_featured' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $link->refresh();
        $this->assertSame($id, $link->id);
        $this->assertSame('Renamed', $link->title);
        $this->assertTrue($link->is_featured);
    }

    public function test_deleting_needs_the_confirmation_box(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));
        $link = $this->link();

        Livewire::test(ListLinks::class)
            ->callTableAction('delete', $link, ['confirmation' => false])
            ->assertHasTableActionErrors(['confirmation']);
        $this->assertSame(1, LinkModel::query()->count());

        Livewire::test(ListLinks::class)
            ->callTableAction('delete', $link, ['confirmation' => true])
            ->assertHasNoTableActionErrors();
        $this->assertSame(0, LinkModel::query()->count());
    }

    public function test_the_list_is_reorderable_by_dragging_and_saves_the_new_order(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));
        $first = $this->link(['title' => 'First']);
        $second = $this->link(['title' => 'Second']);
        $third = $this->link(['title' => 'Third']);

        $this->assertSame('sort_order', $this->table()->getReorderColumn());

        Livewire::test(ListLinks::class)
            ->call('reorderTable', [$third->id, $first->id, $second->id]);

        $this->assertSame(
            ['Third', 'First', 'Second'],
            LinkModel::query()->orderBy('sort_order')->pluck('title')->all(),
        );
    }

    public function test_the_status_column_tells_why_a_link_is_not_showing(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));
        $visible = $this->link(['title' => 'Visible']);
        $off = $this->link(['title' => 'Off', 'is_active' => false]);
        $later = $this->link(['title' => 'Later', 'starts_at' => now()->addDay()]);
        $gone = $this->link(['title' => 'Gone', 'ends_at' => now()->subDay()]);

        Livewire::test(ListLinks::class)
            ->assertTableColumnStateSet('status', 'visible', $visible)
            ->assertTableColumnStateSet('status', 'hidden', $off)
            ->assertTableColumnStateSet('status', 'scheduled', $later)
            ->assertTableColumnStateSet('status', 'expired', $gone);
    }

    public function test_the_row_actions_sit_in_one_menu(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));
        $actions = $this->table()->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function columnBreakpoints(): array
    {
        return [
            'title always shown' => ['title', null],
            'status always shown' => ['status', null],
            'address' => ['url', 'md'],
            'placement' => ['placement', 'xl'],
            'featured' => ['is_featured', 'xl'],
        ];
    }

    #[DataProvider('columnBreakpoints')]
    public function test_secondary_columns_appear_from_the_width_where_they_fit(string $column, ?string $breakpoint): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));

        $this->assertSame($breakpoint, $this->table()->getColumn($column)?->getVisibleFrom());
    }

    public function test_a_long_title_wraps_instead_of_widening_the_table(): void
    {
        $this->actingAs($this->userWithLinkPermissions([self::MANAGE]));
        $column = $this->table()->getColumn('title');

        $this->assertInstanceOf(TextColumn::class, $column);
        $this->assertTrue($column->canWrap());
    }

    private function table(): Table
    {
        $instance = Livewire::test(ListLinks::class)->instance();
        $this->assertInstanceOf(HasTable::class, $instance);

        return $instance->getTable();
    }
}
