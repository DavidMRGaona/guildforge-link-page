<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use Modules\LinkPage\Domain\Enums\ClickTargetType;
use Modules\LinkPage\Domain\Enums\SocialNetwork;
use Modules\LinkPage\Domain\ValueObjects\ClickTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClickTargetTest extends TestCase
{
    private const string UUID = '0199f3a2-7b1c-7d4e-9a10-3c5b2e8f6d41';

    public function test_an_own_link_is_read_with_its_uuid(): void
    {
        $target = ClickTarget::tryFrom('link:'.self::UUID);

        $this->assertNotNull($target);
        $this->assertSame(ClickTargetType::Link, $target->type);
        $this->assertSame(self::UUID, $target->reference);
        $this->assertSame('link:'.self::UUID, $target->value());
    }

    public function test_a_social_icon_is_read_with_its_network(): void
    {
        $target = ClickTarget::tryFrom('social:instagram');

        $this->assertNotNull($target);
        $this->assertSame(ClickTargetType::Social, $target->type);
        $this->assertSame(SocialNetwork::Instagram, $target->network());
    }

    public function test_the_event_buttons_and_the_links_without_reference_are_read(): void
    {
        $this->assertSame(ClickTargetType::Event, ClickTarget::tryFrom('event:'.self::UUID)?->type);
        $this->assertSame(ClickTargetType::Featured, ClickTarget::tryFrom('featured:'.self::UUID)?->type);
        $this->assertSame('home', ClickTarget::tryFrom('home')?->value());

        $notice = ClickTarget::tryFrom('notice');
        $this->assertNotNull($notice);
        $this->assertSame('notice', $notice->value());
        $this->assertSame(ClickTargetType::Notice, $notice->type);
    }

    public function test_a_calendar_entry_keeps_its_source_and_id(): void
    {
        $target = ClickTarget::calendar('game-tables', '42');

        $this->assertSame('calendar:game-tables:42', $target->value());
        $this->assertSame(['game-tables', '42'], $target->calendarEntry());
        $this->assertEquals($target, ClickTarget::tryFrom('calendar:game-tables:42'));
    }

    public function test_an_uppercase_uuid_is_stored_in_lowercase_so_it_counts_once(): void
    {
        $this->assertSame('link:'.self::UUID, ClickTarget::tryFrom('link:'.strtoupper(self::UUID))?->value());
    }

    #[DataProvider('malformed')]
    public function test_anything_else_is_refused(string $value): void
    {
        $this->assertNull(ClickTarget::tryFrom($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown type' => ['qr:'.self::UUID];
        yield 'link without uuid' => ['link:12'];
        yield 'event without uuid' => ['event:'];
        yield 'unknown network' => ['social:myspace'];
        yield 'home with a reference' => ['home:x'];
        yield 'notice with a reference' => ['notice:x'];
        yield 'featured without uuid' => ['featured:next'];
        yield 'calendar without id' => ['calendar:event'];
        yield 'calendar with odd characters' => ['calendar:event:<script>'];
        yield 'too long' => ['calendar:event:'.str_repeat('a', 200)];
    }
}
