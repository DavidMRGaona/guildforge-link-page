<?php

declare(strict_types=1);

namespace Modules\LinkPage\Http\Controllers;

use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\Services\SiteBrandingServiceInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\LinkPage\Application\Contracts\QrCodeImagesInterface;
use Modules\LinkPage\Domain\Enums\PrintPaper;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;

/**
 * A printable A4 or A5 sheet with the site name, the code, its phrase and the short address
 * for people who cannot scan. The browser prints it or saves it as PDF.
 */
final readonly class QrPrintController
{
    /** Drawn as SVG, so the size only sets the grid of the drawing. */
    private const int SVG_SIZE = 1024;

    public function __construct(
        private QrCodeImagesInterface $images,
        private SiteBrandingServiceInterface $branding,
    ) {}

    public function __invoke(QrCodeModel $qrCode, string $paper): Response
    {
        Gate::authorize('view', $qrCode);

        $sheet = PrintPaper::from($paper);
        $url = $this->images->url($qrCode->code);

        return response()
            ->view('link-page::qr-print', [
                'qr' => $qrCode,
                'paper' => $sheet,
                'papers' => PrintPaper::cases(),
                'siteName' => $this->branding->siteName(),
                'tagline' => $qrCode->tagline ?? __('link-page::messages.qr_codes.default_tagline'),
                'svg' => $this->images->render($qrCode->toDesign(), self::SVG_SIZE, QrCodeFormat::Svg)->contents,
                'shortUrl' => preg_replace('#^https?://#', '', $url),
            ])
            ->header('X-Robots-Tag', 'noindex')
            ->header('Cache-Control', 'no-store, private');
    }
}
