<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\Date;
use Modules\LinkPage\Application\Contracts\ClockInterface;

/**
 * The application's own clock (so `travelTo()` in the core tests moves it too).
 */
final readonly class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return Date::now()->toDateTimeImmutable();
    }
}
