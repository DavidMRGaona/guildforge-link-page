<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

/**
 * The sheets a QR code is printed on, upright.
 */
enum PrintPaper: string
{
    case A4 = 'a4';
    case A5 = 'a5';

    /** For CSS @page size. */
    public function cssSize(): string
    {
        return strtoupper($this->value).' portrait';
    }

    /** Side of the printed code in centimetres. */
    public function codeSideCm(): int
    {
        return match ($this) {
            self::A4 => 14,
            self::A5 => 10,
        };
    }

    public function label(): string
    {
        return strtoupper($this->value);
    }
}
