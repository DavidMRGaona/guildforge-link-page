<?php

declare(strict_types=1);

namespace Modules\LinkPage\Application\Contracts;

use DateTimeImmutable;

/**
 * What time it is for the notice and the featured event. A contract so the tests can move the
 * clock to the exact second an event starts or a notice ends.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
