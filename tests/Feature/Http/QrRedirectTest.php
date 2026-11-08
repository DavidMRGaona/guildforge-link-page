<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use Modules\LinkPage\Application\Contracts\QrScanRecorderInterface;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use Modules\LinkPage\Tests\Support\RecordingQrScanRecorder;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * /qr/{code} is what printed posters encode: it only ever leads to the link page (or the home
 * page while the link page is off), never to a destination taken from the request.
 */
final class QrRedirectTest extends ModuleTestCase
{
    use CreatesQrCodes;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    private RecordingQrScanRecorder $scans;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scans = new RecordingQrScanRecorder;
        $this->app->instance(QrScanRecorderInterface::class, $this->scans);
    }

    public function test_an_active_code_leads_to_the_link_page_and_records_the_scan(): void
    {
        $qr = $this->qrCode();

        $this->get('/qr/'.$qr->code)
            ->assertStatus(302)
            ->assertRedirect(route('link_page.show'))
            ->assertHeader('X-Robots-Tag', 'noindex');

        $this->assertSame([$qr->id], $this->scans->ids);
    }

    public function test_the_redirect_is_never_cached_so_every_scan_reaches_the_site(): void
    {
        $qr = $this->qrCode();

        $cacheControl = (string) $this->get('/qr/'.$qr->code)->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_a_code_typed_in_capitals_still_works(): void
    {
        $qr = $this->qrCode();

        $this->get('/qr/'.strtoupper($qr->code))->assertRedirect(route('link_page.show'));
    }

    public function test_a_disabled_code_still_leads_to_the_page_without_counting(): void
    {
        $qr = $this->qrCode(['is_active' => false]);

        $this->get('/qr/'.$qr->code)->assertRedirect(route('link_page.show'));

        $this->assertSame([], $this->scans->ids);
    }

    public function test_a_deleted_code_stays_reserved_and_still_leads_to_the_page(): void
    {
        $qr = $this->qrCode();
        $qr->delete();

        $this->get('/qr/'.$qr->code)->assertRedirect(route('link_page.show'));

        $this->assertSame([], $this->scans->ids);
    }

    public function test_an_unknown_code_is_not_found(): void
    {
        $this->qrCode();

        $this->get('/qr/zzzzzz')->assertNotFound();
    }

    public function test_a_malformed_code_is_not_found(): void
    {
        $this->get('/qr/abc')->assertNotFound();
        $this->get('/qr/k7m2x0')->assertNotFound();
        $this->get('/qr/k7m2xq/extra')->assertNotFound();
    }

    public function test_it_never_follows_a_destination_from_the_request(): void
    {
        $qr = $this->qrCode();

        $this->get('/qr/'.$qr->code.'?to=https://evil.example&next=//evil.example&url=https://evil.example')
            ->assertRedirect(route('link_page.show'));
    }

    public function test_while_the_link_page_is_off_the_code_leads_to_the_home_page(): void
    {
        config()->set('modules.settings.link-page.enabled', false);
        $qr = $this->qrCode();

        $this->get('/qr/'.$qr->code)->assertRedirect(url('/'));
    }

    public function test_a_failing_scan_counter_never_breaks_the_redirect(): void
    {
        $this->app->instance(QrScanRecorderInterface::class, new class implements QrScanRecorderInterface
        {
            public function record(string $qrCodeId): void
            {
                throw new RuntimeException('counter down');
            }
        });
        $qr = $this->qrCode();

        $this->get('/qr/'.$qr->code)->assertRedirect(route('link_page.show'));
    }
}
