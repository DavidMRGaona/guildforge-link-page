<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use Modules\LinkPage\Domain\ValueObjects\ScanCode;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Random\Randomizer;
use RuntimeException;

/**
 * A code no QR code has had, deleted ones included. With 31^6 codes a clash is rare; the
 * unique index catches a race between two panels.
 */
final readonly class ScanCodeGenerator
{
    private const int ATTEMPTS = 20;

    public function __construct(
        private Randomizer $randomizer = new Randomizer,
    ) {}

    public function next(): ScanCode
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $code = ScanCode::generate($this->randomizer);

            if (! QrCodeModel::withTrashed()->where('code', $code->value)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('No free QR code found after '.self::ATTEMPTS.' attempts');
    }
}
