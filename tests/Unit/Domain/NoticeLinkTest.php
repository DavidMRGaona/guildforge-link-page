<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use InvalidArgumentException;
use Modules\LinkPage\Domain\ValueObjects\NoticeLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoticeLinkTest extends TestCase
{
    #[DataProvider('validUrls')]
    public function test_it_accepts_https_addresses_and_site_paths(string $url): void
    {
        $this->assertSame($url, (new NoticeLink($url, null))->url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validUrls(): array
    {
        return [
            'https' => ['https://example.com/news?id=1#top'],
            'a site path' => ['/eventos/jornadas'],
            'the home' => ['/'],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_it_rejects_anything_else(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NoticeLink($url, null);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'uppercase javascript' => ['JavaScript:alert(1)'],
            'data' => ['data:text/html;base64,PHNjcmlwdD4='],
            'plain http' => ['http://example.com'],
            'protocol relative' => ['//evil.example.com/x'],
            'backslash trick' => ['/\\evil.example.com'],
            'no scheme' => ['example.com/news'],
            'mailto' => ['mailto:someone@example.com'],
            'with spaces' => ['https://example.com/a b'],
            'with a control character' => ["https://example.com/\n"],
            'empty' => [''],
            'only a scheme' => ['https://'],
        ];
    }

    public function test_the_label_is_trimmed_and_blank_means_none(): void
    {
        $this->assertSame('More info', (new NoticeLink('/x', '  More info '))->label);
        $this->assertNull((new NoticeLink('/x', '   '))->label);
        $this->assertNull((new NoticeLink('/x', null))->label);
    }

    public function test_it_rejects_a_label_over_the_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NoticeLink('/x', str_repeat('a', NoticeLink::MAX_LABEL_LENGTH + 1));
    }
}
