<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use App\Application\QrCodes\DTOs\QrCodeLogoDTO;
use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\DTOs\RenderedQrCodeDTO;
use App\Application\QrCodes\Enums\QrCodeErrorCorrection;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\QrCodes\Services\QrCodeRendererInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\LinkPage\Application\Contracts\QrCodeImagesInterface;
use Modules\LinkPage\Application\DTOs\QrCodeDesignDTO;
use Modules\LinkPage\Domain\Enums\QrLogoSource;
use Throwable;

final readonly class QrCodeImages implements QrCodeImagesInterface
{
    public function __construct(
        private QrCodeRendererInterface $renderer,
        private SiteBrandingServiceInterface $branding,
    ) {}

    public function render(QrCodeDesignDTO $design, int $size, QrCodeFormat $format): RenderedQrCodeDTO
    {
        $margin = intdiv($size * max(0, min($design->marginPercent, self::MAX_MARGIN_PERCENT)), 100);

        return $this->renderer->render($this->url($design->code), new QrCodeOptionsDTO(
            size: $size,
            margin: $margin,
            // Posters get scuffed: Quartile reads through some damage, a logo raises it to High
            errorCorrection: QrCodeErrorCorrection::Quartile,
            foregroundColor: $design->foregroundColor,
            backgroundColor: $design->backgroundColor,
            logo: $this->logo($design),
        ), $format);
    }

    public function url(string $code): string
    {
        return route('link_page.qr', ['code' => $code]);
    }

    private function logo(QrCodeDesignDTO $design): ?QrCodeLogoDTO
    {
        $path = match ($design->logoSource) {
            QrLogoSource::None => null,
            QrLogoSource::Site => $this->branding->customLogoPath(),
            QrLogoSource::Custom => $design->logoPath,
        };

        if ($path === null || $path === '') {
            return null;
        }

        try {
            $contents = Storage::disk('images')->get($path);

            return is_string($contents) ? new QrCodeLogoDTO($contents) : null;
        } catch (Throwable $exception) {
            // Missing file, unreachable disk or not a usable image (InvalidQrCodeOptionsException)
            Log::warning('QR code logo left out', [
                'module' => 'link-page',
                'code' => $design->code,
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
