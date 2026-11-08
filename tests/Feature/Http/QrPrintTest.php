<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use App\Application\Services\SettingsServiceInterface;
use Illuminate\Support\Str;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The printable A4 and A5 sheets: the browser prints them or saves them as PDF.
 */
final class QrPrintTest extends ModuleTestCase
{
    use CreatesQrCodes;

    private const string MANAGE = 'link-page:qr_codes.manage';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    public function test_guests_are_sent_to_sign_in(): void
    {
        $qr = $this->qrCode();

        $this->get(route('link_page.qr_codes.print', [$qr, 'a4']))->assertRedirect();
    }

    public function test_it_needs_the_qr_codes_permission(): void
    {
        $qr = $this->qrCode();

        $this->actingAs($this->userWith(['link-page:settings.manage']))
            ->get(route('link_page.qr_codes.print', [$qr, 'a4']))
            ->assertForbidden();
    }

    public function test_the_a4_sheet_has_the_site_name_the_code_the_default_phrase_and_the_short_address(): void
    {
        app(SettingsServiceInterface::class)->set('guild_name', 'Example guild');
        $qr = $this->qrCode();

        $response = $this->actingAs($this->userWith([self::MANAGE]))
            ->get(route('link_page.qr_codes.print', [$qr, 'a4']))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex');

        $response->assertSee('Example guild');
        $response->assertSee(__('link-page::messages.qr_codes.default_tagline'));
        // Without the scheme, so it is short enough to type
        $response->assertSee(Str::after(route('link_page.qr', $qr->code), '://'));
        $response->assertSee('size: A4 portrait', false);
        $response->assertSee('<svg', false);
    }

    public function test_the_a5_sheet_uses_the_tagline_of_the_qr_code(): void
    {
        $qr = $this->qrCode(['tagline' => 'Scan for the open days']);

        $response = $this->actingAs($this->userWith([self::MANAGE]))
            ->get(route('link_page.qr_codes.print', [$qr, 'a5']))
            ->assertOk();

        $response->assertSee('Scan for the open days');
        $response->assertSee('size: A5 portrait', false);
    }

    public function test_the_tagline_is_escaped(): void
    {
        $qr = $this->qrCode(['tagline' => '<script>alert(1)</script>']);

        $this->actingAs($this->userWith([self::MANAGE]))
            ->get(route('link_page.qr_codes.print', [$qr, 'a4']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_other_paper_sizes_and_deleted_codes_are_not_found(): void
    {
        $qr = $this->qrCode();
        $user = $this->userWith([self::MANAGE]);

        $this->actingAs($user)->get('/link-page/qr-codes/'.$qr->id.'/print/a3')->assertNotFound();

        $qr->delete();
        $this->actingAs($user)->get(route('link_page.qr_codes.print', [$qr, 'a4']))->assertNotFound();
    }
}
