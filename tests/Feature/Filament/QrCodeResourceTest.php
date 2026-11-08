<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament;

use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Actions\RowDetailsAction;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\LinkPage\Filament\Resources\QrCodeResource;
use Modules\LinkPage\Filament\Resources\QrCodeResource\Pages\CreateQrCode;
use Modules\LinkPage\Filament\Resources\QrCodeResource\Pages\EditQrCode;
use Modules\LinkPage\Filament\Resources\QrCodeResource\Pages\ListQrCodes;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Modules\LinkPage\LinkPageServiceProvider;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\QrCodes\QrCodeTestImages;

final class QrCodeResourceTest extends ModuleTestCase
{
    use CreatesQrCodes;
    use InteractsWithModulePanel;

    private const string MANAGE = 'link-page:qr_codes.manage';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([QrCodeResource::class]);
    }

    public function test_the_provider_registers_the_resource_the_policy_and_the_permission(): void
    {
        $provider = new LinkPageServiceProvider(app());
        $permissions = array_map(fn (PermissionDTO $permission): string => $permission->name, $provider->registerPermissions());

        $this->assertContains(QrCodeResource::class, $provider->registerFilamentResources());
        $this->assertArrayHasKey(QrCodeModel::class, $provider->registerPolicies());
        $this->assertContains('qr_codes.manage', $permissions);
    }

    public function test_only_qr_code_managers_open_the_list(): void
    {
        $this->actingAs($this->userWith(['link-page:settings.manage']));
        $this->assertFalse(QrCodeResource::canViewAny());
        Livewire::test(ListQrCodes::class)->assertForbidden();

        $this->actingAs($this->userWith([self::MANAGE]));
        $this->assertTrue(QrCodeResource::canViewAny());
        Livewire::test(ListQrCodes::class)->assertOk();
    }

    public function test_nobody_can_restore_or_destroy_a_deleted_code_so_it_stays_reserved(): void
    {
        $admin = $this->actingAsPanelAdmin();
        $qr = $this->qrCode();
        $qr->delete();

        $this->assertFalse(Gate::forUser($admin)->allows('restore', $qr));
        $this->assertFalse(Gate::forUser($admin)->allows('forceDelete', $qr));
    }

    public function test_it_creates_a_qr_code_with_its_own_code(): void
    {
        $this->actingAs($this->userWith([self::MANAGE]));

        Livewire::test(CreateQrCode::class)
            ->fillForm([
                'name' => 'Table cards',
                'tagline' => 'Scan for the next games',
                'foreground_color' => '#1F2937',
                'background_color' => '#FFFFFF',
                'margin' => 6,
                'logo_source' => 'none',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $qr = QrCodeModel::query()->sole();
        $this->assertSame('Table cards', $qr->name);
        $this->assertSame('Scan for the next games', $qr->tagline);
        $this->assertSame(6, $qr->margin);
        $this->assertSame(6, strlen($qr->code));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unreadableColours(): array
    {
        return [
            'too little contrast' => ['#666666', '#888888'],
            'light on dark' => ['#FFFFFF', '#000000'],
        ];
    }

    #[DataProvider('unreadableColours')]
    public function test_colours_a_phone_could_not_read_are_not_saved(string $foreground, string $background): void
    {
        $this->actingAs($this->userWith([self::MANAGE]));

        Livewire::test(CreateQrCode::class)
            ->fillForm([
                'name' => 'Poster',
                'foreground_color' => $foreground,
                'background_color' => $background,
                'margin' => 8,
                'logo_source' => 'none',
            ])
            ->call('create')
            ->assertHasFormErrors(['background_color']);

        $this->assertSame(0, QrCodeModel::query()->count());
    }

    public function test_the_preview_draws_the_code_or_says_why_it_cannot(): void
    {
        $this->actingAs($this->userWith([self::MANAGE]));

        Livewire::test(CreateQrCode::class)
            ->assertSee('data:image/svg+xml', false)
            ->assertDontSee(__('link-page::messages.qr_codes.preview_unavailable'))
            ->fillForm(['background_color' => '#444444'])
            ->assertSee(__('link-page::messages.qr_codes.preview_unavailable'))
            ->assertDontSee('data:image/svg+xml', false);
    }

    public function test_a_logo_just_uploaded_shows_in_the_preview_before_saving(): void
    {
        Storage::fake('images');
        $this->actingAs($this->userWith([self::MANAGE]));
        $logo = UploadedFile::fake()->createWithContent('logo.png', QrCodeTestImages::png(120, 120))->mimeType('image/png');

        $page = Livewire::test(CreateQrCode::class)
            ->fillForm(['logo_source' => 'custom']);
        $this->assertStringNotContainsString('<image', $this->previewSvg($page->html()));

        $page->fillForm(['logo_path' => $logo]);

        $this->assertStringContainsString('<image', $this->previewSvg($page->html()));
        $page->assertDontSee(__('link-page::messages.qr_codes.preview_logo_unusable'));
        // Nothing is kept until the code is created
        $this->assertSame([], Storage::disk('images')->allFiles());
        $this->assertSame(0, QrCodeModel::query()->count());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unusableUploads(): iterable
    {
        yield 'not an image' => ['logo.txt', 'text/plain'];
        yield 'a GIF' => ['logo.gif', 'image/gif'];
    }

    #[DataProvider('unusableUploads')]
    public function test_an_upload_the_logo_cannot_use_is_reported_in_the_preview(string $name, string $mimeType): void
    {
        Storage::fake('images');
        $this->actingAs($this->userWith([self::MANAGE]));
        $content = $mimeType === 'image/gif' ? QrCodeTestImages::gif(120, 120) : 'not an image';

        $page = Livewire::test(CreateQrCode::class)
            ->fillForm([
                'logo_source' => 'custom',
                'logo_path' => UploadedFile::fake()->createWithContent($name, $content)->mimeType($mimeType),
            ]);

        $page->assertSee(__('link-page::messages.qr_codes.preview_logo_unusable'));
        // The code itself is still drawn, without the logo
        $this->assertStringNotContainsString('<image', $this->previewSvg($page->html()));
    }

    public function test_an_upload_too_large_for_the_logo_is_reported_in_the_preview(): void
    {
        Storage::fake('images');
        $this->actingAs($this->userWith([self::MANAGE]));
        $logo = UploadedFile::fake()->createWithContent('logo.png', QrCodeTestImages::png(2100, 40))->mimeType('image/png');

        Livewire::test(CreateQrCode::class)
            ->fillForm(['logo_source' => 'custom', 'logo_path' => $logo])
            ->assertSee(__('link-page::messages.qr_codes.preview_logo_unusable'));
    }

    public function test_an_svg_site_logo_shows_in_the_preview(): void
    {
        Storage::fake('images');
        Storage::disk('images')->put('branding/logo.svg', QrCodeTestImages::svg(200, 100));
        app(SettingsServiceInterface::class)->set('site_logo_light', 'branding/logo.svg');
        $this->actingAs($this->userWith([self::MANAGE]));

        $page = Livewire::test(CreateQrCode::class)
            ->fillForm(['logo_source' => 'site']);

        $this->assertStringContainsString('href="data:image/svg+xml;base64,', $this->previewSvg($page->html()));
        $page->assertDontSee(__('link-page::messages.qr_codes.preview_site_logo_unusable'));
    }

    public function test_a_site_logo_the_qr_codes_cannot_use_is_reported_in_the_preview(): void
    {
        Storage::fake('images');
        Storage::disk('images')->put('branding/logo.svg', '<!DOCTYPE svg>'.QrCodeTestImages::svg(200, 100));
        app(SettingsServiceInterface::class)->set('site_logo_light', 'branding/logo.svg');
        $this->actingAs($this->userWith([self::MANAGE]));

        $page = Livewire::test(CreateQrCode::class)
            ->assertDontSee(__('link-page::messages.qr_codes.preview_site_logo_unusable'))
            ->fillForm(['logo_source' => 'site'])
            ->assertSee(__('link-page::messages.qr_codes.preview_site_logo_unusable'));

        $this->assertStringNotContainsString('<image', $this->previewSvg($page->html()));
    }

    public function test_an_svg_just_uploaded_shows_in_the_preview_sanitized(): void
    {
        Storage::fake('images');
        $this->actingAs($this->userWith([self::MANAGE]));
        $logo = UploadedFile::fake()
            ->createWithContent('logo.svg', QrCodeTestImages::svg(120, 120, '<script>alert(1)</script>'))
            ->mimeType('image/svg+xml');

        $page = Livewire::test(CreateQrCode::class)
            ->fillForm(['logo_source' => 'custom', 'logo_path' => $logo]);

        $preview = $this->previewSvg($page->html());
        $this->assertSame(1, preg_match('#<image[^>]+href="data:image/svg\+xml;base64,([A-Za-z0-9+/=]+)"#', $preview, $match));
        $this->assertStringNotContainsString('script', (string) base64_decode($match[1], true));
        $page->assertDontSee(__('link-page::messages.qr_codes.preview_logo_unusable'));
    }

    /**
     * Uploaded logos sit on the public images disk: only the sanitized markup is kept.
     */
    public function test_an_uploaded_svg_is_stored_sanitized_with_its_extension(): void
    {
        Storage::fake('images');
        $this->actingAs($this->userWith([self::MANAGE]));
        $logo = UploadedFile::fake()
            ->createWithContent('logo.html', QrCodeTestImages::svg(120, 80, '<script>alert(1)</script><a href="https://example.com"><rect width="1" height="1"/></a>'))
            ->mimeType('image/svg+xml');

        Livewire::test(CreateQrCode::class)
            ->fillForm($this->validForm(['logo_source' => 'custom', 'logo_path' => $logo]))
            ->call('create')
            ->assertHasNoFormErrors();

        $path = (string) QrCodeModel::query()->sole()->logo_path;
        $this->assertMatchesRegularExpression('#^link-page/qr/\d{4}/\d{2}/[0-9a-f-]{36}\.svg$#', $path);
        $stored = (string) Storage::disk('images')->get($path);
        $this->assertStringContainsString('#C81E1E', $stored);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('example.com', $stored);
        $this->assertSame([$path], Storage::disk('images')->allFiles());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableSvgs(): iterable
    {
        yield 'with a DOCTYPE' => ['<!DOCTYPE svg>'.QrCodeTestImages::svg(120, 120)];
        yield 'with entities' => ['<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "b">]><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1">&a;</svg>'];
        yield 'badly formed' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><rect></svg>'];
        yield 'without a size' => ['<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><rect width="1" height="1"/></svg>'];
    }

    #[DataProvider('unusableSvgs')]
    public function test_an_svg_the_logo_cannot_use_is_reported_and_not_saved(string $svg): void
    {
        Storage::fake('images');
        $this->actingAs($this->userWith([self::MANAGE]));
        $logo = UploadedFile::fake()->createWithContent('logo.svg', $svg)->mimeType('image/svg+xml');

        Livewire::test(CreateQrCode::class)
            ->fillForm($this->validForm(['logo_source' => 'custom', 'logo_path' => $logo]))
            ->assertSee(__('link-page::messages.qr_codes.preview_logo_unusable'))
            ->call('create')
            ->assertHasFormErrors(['logo_path'])
            ->assertSee(__('link-page::messages.qr_codes.validation.logo_unusable'));

        $this->assertSame(0, QrCodeModel::query()->count());
    }

    public function test_a_colour_must_be_a_hex_value(): void
    {
        $this->actingAs($this->userWith([self::MANAGE]));

        Livewire::test(CreateQrCode::class)
            ->fillForm([
                'name' => 'Poster',
                'foreground_color' => 'black',
                'background_color' => '#FFFFFF',
                'margin' => 8,
                'logo_source' => 'none',
            ])
            ->call('create')
            ->assertHasFormErrors(['foreground_color']);
    }

    public function test_an_uploaded_logo_is_required_when_chosen(): void
    {
        $this->actingAs($this->userWith([self::MANAGE]));

        Livewire::test(CreateQrCode::class)
            ->fillForm([
                'name' => 'Poster',
                'foreground_color' => '#000000',
                'background_color' => '#FFFFFF',
                'margin' => 8,
                'logo_source' => 'custom',
            ])
            ->call('create')
            ->assertHasFormErrors(['logo_path']);
    }

    /**
     * The stored name comes from the checked content, never from the name the browser sent.
     */
    public function test_an_uploaded_logo_is_stored_with_the_extension_of_its_content(): void
    {
        Storage::fake('images');
        $this->actingAs($this->userWith([self::MANAGE]));
        // Laravel's fake files take their MIME type from the name unless told; a real upload's
        // comes from its content, a PNG here
        $logo = UploadedFile::fake()->createWithContent('logo.html', QrCodeTestImages::png(120, 120))->mimeType('image/png');

        Livewire::test(CreateQrCode::class)
            ->fillForm([
                'name' => 'Poster',
                'foreground_color' => '#000000',
                'background_color' => '#FFFFFF',
                'margin' => 8,
                'logo_source' => 'custom',
                'logo_path' => $logo,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $path = (string) QrCodeModel::query()->sole()->logo_path;
        $this->assertMatchesRegularExpression('#^link-page/qr/\d{4}/\d{2}/[0-9a-f-]{36}\.png$#', $path);
        Storage::disk('images')->assertExists($path);
        $this->assertSame([], array_values(array_filter(
            Storage::disk('images')->allFiles(),
            fn (string $file): bool => str_ends_with($file, '.html'),
        )));
    }

    public function test_it_edits_a_qr_code_without_changing_its_code(): void
    {
        $this->actingAs($this->userWith([self::MANAGE]));
        $qr = $this->qrCode();
        $code = $qr->code;

        Livewire::test(EditQrCode::class, ['record' => $qr->getRouteKey()])
            ->fillForm(['name' => 'Window poster'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Window poster', $qr->refresh()->name);
        $this->assertSame($code, $qr->code);
    }

    public function test_a_qr_code_can_be_disabled_and_enabled_again(): void
    {
        $this->actingAs($this->userWith([self::MANAGE]));
        $qr = $this->qrCode();

        Livewire::test(ListQrCodes::class)->callTableAction('deactivate', $qr);
        $this->assertFalse($qr->refresh()->is_active);

        Livewire::test(ListQrCodes::class)->callTableAction('activate', $qr);
        $this->assertTrue($qr->refresh()->is_active);
    }

    public function test_deleting_needs_the_confirmation_box(): void
    {
        $this->actingAs($this->userWith([self::MANAGE]));
        $qr = $this->qrCode();

        Livewire::test(ListQrCodes::class)
            ->callTableAction('delete', $qr, ['confirmation' => false])
            ->assertHasTableActionErrors(['confirmation']);
        $this->assertFalse($qr->refresh()->trashed());

        Livewire::test(ListQrCodes::class)
            ->callTableAction('delete', $qr, ['confirmation' => true])
            ->assertHasNoTableActionErrors();
        $this->assertTrue($qr->refresh()->trashed());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function downloads(): array
    {
        return [
            'svg' => ['svg', '.svg', 'image/svg+xml'],
            'png 256' => ['png-256', '-256.png', 'image/png'],
            'png 2048' => ['png-2048', '-2048.png', 'image/png'],
        ];
    }

    #[DataProvider('downloads')]
    public function test_it_downloads_the_image(string $format, string $suffix, string $mimeType): void
    {
        $this->actingAs($this->userWith([self::MANAGE]));
        $qr = $this->qrCode();

        Livewire::test(ListQrCodes::class)
            ->callTableAction('download', $qr, ['format' => $format])
            ->assertFileDownloaded('qr-'.$qr->code.$suffix, contentType: $mimeType);
    }

    public function test_the_print_actions_open_the_sheets(): void
    {
        $this->actingAs($this->userWith([self::MANAGE]));
        $qr = $this->qrCode();
        $table = $this->table();

        $this->assertSame(route('link_page.qr_codes.print', [$qr, 'a4']), $table->getAction('print_a4')?->record($qr)->getUrl());
        $this->assertSame(route('link_page.qr_codes.print', [$qr, 'a5']), $table->getAction('print_a5')?->record($qr)->getUrl());
    }

    public function test_the_menu_starts_with_the_row_details(): void
    {
        $this->actingAsPanelAdmin();
        $table = $this->table();
        $group = $table->getRecordActions()[0];

        $this->assertInstanceOf(ActionGroup::class, $group);
        $this->assertSame(RowDetailsAction::NAME, array_key_first($group->getFlatActions()));
        $this->assertNotNull($table->getAction(RowDetailsAction::NAME));
    }

    public function test_the_row_actions_sit_in_one_menu(): void
    {
        $this->actingAsPanelAdmin();
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
            'name always shown' => ['name', null],
            'code always shown' => ['code', null],
            'active' => ['is_active', 'sm'],
            'updated' => ['updated_at', 'md'],
        ];
    }

    #[DataProvider('columnBreakpoints')]
    public function test_secondary_columns_appear_from_the_width_where_they_fit(string $column, ?string $breakpoint): void
    {
        $this->actingAsPanelAdmin();

        $this->assertSame($breakpoint, $this->table()->getColumn($column)?->getVisibleFrom());
    }

    public function test_a_long_name_wraps_instead_of_widening_the_table(): void
    {
        $this->actingAsPanelAdmin();
        $column = $this->table()->getColumn('name');

        $this->assertInstanceOf(TextColumn::class, $column);
        $this->assertTrue($column->canWrap());
    }

    private function table(): Table
    {
        $instance = Livewire::test(ListQrCodes::class)->instance();
        $this->assertInstanceOf(HasTable::class, $instance);

        return $instance->getTable();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Poster',
            'foreground_color' => '#000000',
            'background_color' => '#FFFFFF',
            'margin' => 8,
            'logo_source' => 'none',
        ], $overrides);
    }

    /**
     * The SVG drawn in the form's preview.
     */
    private function previewSvg(string $html): string
    {
        $this->assertSame(1, preg_match('#data:image/svg\+xml;base64,([A-Za-z0-9+/=]+)#', $html, $match));

        return (string) base64_decode($match[1], true);
    }
}
