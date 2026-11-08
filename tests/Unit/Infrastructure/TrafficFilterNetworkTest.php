<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Infrastructure;

use Modules\LinkPage\Infrastructure\Stats\TrafficFilter;
use PHPUnit\Framework\TestCase;

final class TrafficFilterNetworkTest extends TestCase
{
    public function test_an_ipv4_address_is_kept_whole(): void
    {
        $this->assertSame('203.0.113.7', TrafficFilter::network('203.0.113.7'));
        $this->assertNotSame(TrafficFilter::network('203.0.113.7'), TrafficFilter::network('203.0.113.8'));
    }

    public function test_every_address_of_one_ipv6_subscriber_is_the_same(): void
    {
        $this->assertSame('2001:db8:1:2::', TrafficFilter::network('2001:db8:1:2:aaaa:bbbb:cccc:dddd'));
        $this->assertSame(TrafficFilter::network('2001:db8:1:2::1'), TrafficFilter::network('2001:db8:1:2:ffff::9'));
        $this->assertNotSame(TrafficFilter::network('2001:db8:1:2::1'), TrafficFilter::network('2001:db8:1:3::1'));
    }

    public function test_an_ipv4_address_written_as_ipv6_is_still_whole(): void
    {
        $this->assertNotSame(TrafficFilter::network('::ffff:203.0.113.7'), TrafficFilter::network('::ffff:203.0.113.8'));
    }

    public function test_something_that_is_no_address_is_left_as_it_is(): void
    {
        $this->assertSame('', TrafficFilter::network(null));
        $this->assertSame('unknown', TrafficFilter::network('unknown'));
    }
}
