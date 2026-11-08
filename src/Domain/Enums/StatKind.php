<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

/**
 * What a counter of the link page counts.
 */
enum StatKind: string
{
    /** One load of /enlaces, reloads and back or forward steps left out. */
    case Visit = 'visit';

    /** One scan of a printed QR code (/qr/{code}). */
    case Scan = 'scan';

    /** One click on something of the page that has a stable id. */
    case Click = 'click';
}
