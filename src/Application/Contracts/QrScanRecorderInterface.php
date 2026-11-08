<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Contracts;

/**
 * Counts a scan of an active QR code on its way to the link page. Called by
 * QrRedirectController before the redirect, never for disabled or deleted codes; a failure
 * is reported and the redirect still happens, so an implementation must not rely on
 * stopping it. Only the id is given: no IP, user, user agent or cookie may be stored.
 */
interface QrScanRecorderInterface
{
    public function record(string $qrCodeId): void;
}
