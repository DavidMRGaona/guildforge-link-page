<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

/**
 * The colour pairs a colour set must keep readable (WCAG 2.2 AA): text 4.5:1 (1.4.3), the edge
 * of a filled button 3:1 against the page (1.4.11).
 */
enum ContrastPair: string
{
    /** The page text on the page background. */
    case Text = 'text';

    /** The button text on the button. */
    case ButtonText = 'button_text';

    /** A filled or shadowed button against the page background. */
    case ButtonEdge = 'button_edge';

    /** An outline button: its text, in the button colour, on the page background. */
    case ButtonOutline = 'button_outline';

    public function minimum(): float
    {
        return $this === self::ButtonEdge ? 3.0 : 4.5;
    }

    /** The colour field the failure belongs to. */
    public function field(): string
    {
        return match ($this) {
            self::Text => 'text',
            self::ButtonText => 'button_text',
            self::ButtonEdge, self::ButtonOutline => 'button',
        };
    }
}
