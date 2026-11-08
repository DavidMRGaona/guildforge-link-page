<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\DTOs;

use Modules\LinkPage\Domain\Enums\QrLogoSource;

/**
 * How one QR code looks, whatever size or format it is drawn in.
 */
final readonly class QrCodeDesignDTO
{
    /**
     * @param  string  $code  The stable code the image encodes in /qr/{code}
     * @param  int  $marginPercent  Quiet zone as a share of the whole image, so every size looks alike
     * @param  string|null  $logoPath  Path on the images disk; only read when the source is Custom
     * @param  string|null  $logoContents  A Custom logo not stored yet (the form's preview of an upload);
     *                                     used instead of $logoPath when given
     */
    public function __construct(
        public string $code,
        public string $foregroundColor,
        public string $backgroundColor,
        public int $marginPercent,
        public QrLogoSource $logoSource = QrLogoSource::None,
        public ?string $logoPath = null,
        public ?string $logoContents = null,
    ) {}

    public function withoutLogo(): self
    {
        return new self(
            code: $this->code,
            foregroundColor: $this->foregroundColor,
            backgroundColor: $this->backgroundColor,
            marginPercent: $this->marginPercent,
        );
    }
}
