<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

use Modules\LinkPage\Domain\Enums\Concerns\HasTranslatedOptions;

/**
 * The short list of fonts for the page. None loads anything new: the site fonts come with the
 * site theme the core puts in every page head, Inter and Playfair Display are loaded by the
 * core's root view on every page, and the rest are the visitor's own system fonts.
 */
enum PageFont: string
{
    use HasTranslatedOptions;

    case SiteHeading = 'site_heading';
    case SiteBody = 'site_body';
    case Inter = 'inter';
    case Playfair = 'playfair';
    case SystemSans = 'system_sans';
    case SystemSerif = 'system_serif';
    case SystemMono = 'system_mono';

    /** The CSS font-family value; fixed strings, never built from stored values. */
    public function stack(): string
    {
        return match ($this) {
            self::SiteHeading => 'var(--font-heading, system-ui, sans-serif)',
            self::SiteBody => 'var(--font-body, system-ui, sans-serif)',
            self::Inter => "'Inter', system-ui, sans-serif",
            self::Playfair => "'Playfair Display', ui-serif, Georgia, serif",
            self::SystemSans => "system-ui, -apple-system, 'Segoe UI', sans-serif",
            self::SystemSerif => "ui-serif, Georgia, 'Times New Roman', serif",
            self::SystemMono => "ui-monospace, Menlo, Consolas, 'Liberation Mono', monospace",
        };
    }

    protected static function translationGroup(): string
    {
        return 'fonts';
    }
}
