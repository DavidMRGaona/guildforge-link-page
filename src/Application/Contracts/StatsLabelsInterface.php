<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Contracts;

use Modules\LinkPage\Domain\ValueObjects\ClickTarget;

/**
 * Names for the rankings of the panel, read when they are shown: the statistics only keep ids,
 * so a renamed link shows its new title and a deleted one says so.
 */
interface StatsLabelsInterface
{
    /**
     * The name of each QR code and whether it still counts (`active`), is switched off
     * (`inactive`) or deleted (`deleted`); an id without a row is a deleted code.
     *
     * @param  list<string>  $qrCodeIds
     * @return array<string, array{name: string, state: 'active'|'inactive'|'deleted'}>
     */
    public function qrCodes(array $qrCodeIds): array;

    /**
     * What each clicked element is called now.
     *
     * @param  list<ClickTarget>  $targets
     * @return array<string, string> by `ClickTarget::value()`
     */
    public function targets(array $targets): array;
}
