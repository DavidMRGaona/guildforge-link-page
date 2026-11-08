<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\LinkPage\Infrastructure\Stats\TrafficFilter;
use Modules\LinkPage\Tests\Support\CreatesQrCodes;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Scans as the statistics count them: one per device and code within the repeat window, never
 * an automated agent, and the redirect never depends on it.
 */
final class QrScanCountingTest extends ModuleTestCase
{
    use CreatesQrCodes;

    private const string PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1';

    private const string OTHER_PHONE = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_a_scan_from_a_phone_counts_for_its_code(): void
    {
        $qr = $this->qrCode();

        $this->scan($qr->code, self::PHONE)->assertRedirect(route('link_page.show'));

        $this->assertSame(1, $this->scansOf($qr->id));
    }

    public function test_a_crawler_or_a_link_preview_is_redirected_without_counting(): void
    {
        $qr = $this->qrCode();

        $this->scan($qr->code, 'WhatsApp/2.23.20.0')->assertRedirect(route('link_page.show'));
        $this->scan($qr->code, '')->assertRedirect(route('link_page.show'));

        $this->assertSame(0, $this->scansOf($qr->id));
    }

    public function test_a_prefetch_does_not_count(): void
    {
        $qr = $this->qrCode();

        $this->withHeader('Sec-Purpose', 'prefetch')->scan($qr->code, self::PHONE);

        $this->assertSame(0, $this->scansOf($qr->id));
    }

    public function test_the_same_phone_scanning_again_within_seconds_counts_once(): void
    {
        CarbonImmutable::setTestNow('2026-11-08 12:00:00');
        $qr = $this->qrCode();

        $this->scan($qr->code, self::PHONE);
        $this->scan($qr->code, self::PHONE);
        $this->scan($qr->code, self::OTHER_PHONE);

        $this->assertSame(2, $this->scansOf($qr->id));

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(TrafficFilter::REPEAT_WINDOW['scan'] + 1));
        $this->scan($qr->code, self::PHONE);

        $this->assertSame(3, $this->scansOf($qr->id));
    }

    public function test_one_device_scanning_code_after_code_stops_counting_but_is_still_redirected(): void
    {
        $codes = [];
        for ($i = 0; $i <= TrafficFilter::SCANS_PER_MINUTE; $i++) {
            $codes[] = $this->qrCode(['name' => 'Poster '.$i])->code;
        }

        foreach ($codes as $code) {
            $this->scan($code, self::PHONE)->assertRedirect(route('link_page.show'));
        }

        $this->assertSame(TrafficFilter::SCANS_PER_MINUTE, (int) DB::table('link_page_stats')->where('kind', 'scan')->sum('count'));
    }

    private function scan(string $code, string $userAgent): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('User-Agent', $userAgent)->get('/qr/'.$code);
    }

    private function scansOf(string $qrCodeId): int
    {
        return (int) DB::table('link_page_stats')->where('kind', 'scan')->where('subject', $qrCodeId)->sum('count');
    }
}
