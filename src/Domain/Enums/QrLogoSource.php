<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

/**
 * What goes in the centre of a QR code.
 */
enum QrLogoSource: string
{
    case None = 'none';
    case Site = 'site';
    case Custom = 'custom';

    public function label(): string
    {
        return __('link-page::messages.qr_codes.logo_sources.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
