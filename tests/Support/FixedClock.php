<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Support;

use DateTimeImmutable;
use Modules\LinkPage\Application\Contracts\ClockInterface;

/**
 * A clock the test moves by hand, to try the edges of the notice window and of the featured event.
 */
final class FixedClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $iso)
    {
        $this->now = new DateTimeImmutable($iso);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(string $iso): void
    {
        $this->now = new DateTimeImmutable($iso);
    }
}
