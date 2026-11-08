<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\LinkPage\Domain\Enums\NoticeTone;
use Modules\LinkPage\Domain\ValueObjects\Notice;
use Modules\LinkPage\Domain\ValueObjects\NoticeLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoticeTest extends TestCase
{
    public function test_it_trims_the_text(): void
    {
        $notice = new Notice('  The venue is closed on Saturday.  ', NoticeTone::Warning);

        $this->assertSame('The venue is closed on Saturday.', $notice->text);
    }

    public function test_it_rejects_a_blank_text(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Notice("  \n ", NoticeTone::Info);
    }

    public function test_it_rejects_a_text_over_the_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Notice(str_repeat('a', Notice::MAX_TEXT_LENGTH + 1), NoticeTone::Info);
    }

    public function test_it_accepts_a_text_of_exactly_the_limit(): void
    {
        $notice = new Notice(str_repeat('a', Notice::MAX_TEXT_LENGTH), NoticeTone::Info);

        $this->assertSame(Notice::MAX_TEXT_LENGTH, mb_strlen($notice->text));
    }

    public function test_it_rejects_an_end_that_is_not_after_the_start(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Notice('Hello', NoticeTone::Info, null, $this->at('2026-11-10T10:00:00Z'), $this->at('2026-11-10T10:00:00Z'));
    }

    public function test_without_dates_it_is_always_visible(): void
    {
        $notice = new Notice('Hello', NoticeTone::Info);

        $this->assertTrue($notice->isVisibleAt($this->at('2000-01-01T00:00:00Z')));
        $this->assertTrue($notice->isVisibleAt($this->at('2999-01-01T00:00:00Z')));
    }

    #[DataProvider('windowEdges')]
    public function test_it_is_visible_from_its_start_until_just_before_its_end(string $now, bool $visible): void
    {
        $notice = new Notice('Hello', NoticeTone::Info, null, $this->at('2026-11-10T10:00:00Z'), $this->at('2026-11-12T10:00:00Z'));

        $this->assertSame($visible, $notice->isVisibleAt($this->at($now)));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function windowEdges(): array
    {
        return [
            'one second before the start' => ['2026-11-10T09:59:59Z', false],
            'at the start' => ['2026-11-10T10:00:00Z', true],
            'inside' => ['2026-11-11T00:00:00Z', true],
            'one second before the end' => ['2026-11-12T09:59:59Z', true],
            'at the end' => ['2026-11-12T10:00:00Z', false],
        ];
    }

    public function test_with_only_a_start_it_stays_visible_afterwards(): void
    {
        $notice = new Notice('Hello', NoticeTone::Info, null, $this->at('2026-11-10T10:00:00Z'));

        $this->assertFalse($notice->isVisibleAt($this->at('2026-11-10T09:00:00Z')));
        $this->assertTrue($notice->isVisibleAt($this->at('2030-01-01T00:00:00Z')));
    }

    public function test_with_only_an_end_it_is_visible_until_then(): void
    {
        $notice = new Notice('Hello', NoticeTone::Info, null, null, $this->at('2026-11-10T10:00:00Z'));

        $this->assertTrue($notice->isVisibleAt($this->at('2000-01-01T00:00:00Z')));
        $this->assertFalse($notice->isVisibleAt($this->at('2026-11-10T10:00:00Z')));
    }

    public function test_the_window_is_compared_by_instant_not_by_zone(): void
    {
        $notice = new Notice('Hello', NoticeTone::Info, null, $this->at('2026-11-10T11:00:00+01:00'), $this->at('2026-11-10T12:00:00+01:00'));

        $this->assertTrue($notice->isVisibleAt($this->at('2026-11-10T10:30:00Z')));
        $this->assertFalse($notice->isVisibleAt($this->at('2026-11-10T11:30:00Z')));
    }

    public function test_it_round_trips_through_its_stored_shape(): void
    {
        $notice = new Notice(
            'Hello',
            NoticeTone::Important,
            new NoticeLink('https://example.com/news', 'Read more'),
            $this->at('2026-11-10T10:00:00+01:00'),
            $this->at('2026-11-12T10:00:00+01:00'),
        );

        $copy = Notice::fromArray($notice->toArray());

        $this->assertSame($notice->toArray(), $copy->toArray());
        $this->assertSame('Hello', $copy->text);
        $this->assertSame(NoticeTone::Important, $copy->tone);
        $this->assertSame('https://example.com/news', $copy->link?->url);
        $this->assertEquals($notice->startsAt, $copy->startsAt);
    }

    public function test_the_stored_shape_keeps_instants_in_iso_8601(): void
    {
        $notice = new Notice('Hello', NoticeTone::Info, null, $this->at('2026-11-10T10:00:00+01:00'));

        $this->assertSame('2026-11-10T10:00:00+01:00', $notice->toArray()['starts_at']);
        $this->assertNull($notice->toArray()['ends_at']);
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    #[DataProvider('malformed')]
    public function test_a_malformed_stored_value_is_rejected(array $stored): void
    {
        $this->expectException(InvalidArgumentException::class);

        Notice::fromArray($stored);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function malformed(): array
    {
        return [
            'no text' => [['tone' => 'info']],
            'text is not a string' => [['text' => 5, 'tone' => 'info']],
            'unknown tone' => [['text' => 'Hello', 'tone' => 'shout']],
            'bad date' => [['text' => 'Hello', 'tone' => 'info', 'starts_at' => 'tomorrow-ish']],
            'bad link' => [['text' => 'Hello', 'tone' => 'info', 'link_url' => 'javascript:alert(1)']],
        ];
    }

    private function at(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso);
    }
}
