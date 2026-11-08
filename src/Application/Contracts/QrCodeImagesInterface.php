<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Contracts;

use App\Application\QrCodes\DTOs\RenderedQrCodeDTO;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeOptionsException;
use App\Domain\QrCodes\Exceptions\QrCodeRenderingException;
use Modules\LinkPage\Application\DTOs\QrCodeDesignDTO;

/**
 * Draws a QR code of the module with the core renderer. The image always encodes the stable
 * address /qr/{code}, never the link page itself.
 */
interface QrCodeImagesInterface
{
    /** PNG sizes offered for download, in pixels. */
    public const array PNG_SIZES = [256, 512, 1024, 2048];

    public const int MAX_MARGIN_PERCENT = 20;

    /**
     * A logo that cannot be read (missing file, not an image) is left out rather than
     * failing: a poster is better without a logo than not at all.
     *
     * @throws InvalidQrCodeOptionsException when the colours or the size are not allowed
     * @throws QrCodeRenderingException
     */
    public function render(QrCodeDesignDTO $design, int $size, QrCodeFormat $format): RenderedQrCodeDTO;

    /** The address a code encodes. */
    public function url(string $code): string;

    /**
     * True when the site has a logo that QR codes would leave out: missing, unreadable or
     * an SVG the core rejects. False without a site logo.
     */
    public function siteLogoIsUnusable(): bool;
}
