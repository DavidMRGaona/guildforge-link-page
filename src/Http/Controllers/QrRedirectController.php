<?php

declare(strict_types=1);

namespace Modules\LinkPage\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\Contracts\QrScanRecorderInterface;
use Modules\LinkPage\Domain\ValueObjects\ScanCode;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Throwable;

/**
 * /qr/{code}, the address printed QR codes encode. The destination is fixed (the link page,
 * or the home page while it is off) and nothing from the request can change it, so this is
 * no open redirect. Disabled and deleted codes still lead there: a printed poster never
 * shows an error. Only unknown codes are a 404.
 */
final readonly class QrRedirectController
{
    public function __construct(
        private LinkPageSettingsInterface $settings,
        private QrScanRecorderInterface $scans,
    ) {}

    public function __invoke(string $code): RedirectResponse
    {
        abort_unless(ScanCode::isValid($code), 404);

        $qr = QrCodeModel::withTrashed()->where('code', ScanCode::fromString($code)->value)->first();
        abort_if($qr === null, 404);

        if ($qr->is_active && ! $qr->trashed()) {
            try {
                $this->scans->record($qr->id);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $destination = $this->settings->isEnabled() ? route('link_page.show') : url('/');

        // 302 and no-store: a cached redirect would skip the site and the scan count
        return redirect()->to($destination, 302)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex');
    }
}
