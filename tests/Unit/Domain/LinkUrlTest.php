<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use InvalidArgumentException;
use Modules\LinkPage\Domain\ValueObjects\LinkUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LinkUrlTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function acceptedProvider(): array
    {
        return [
            'https with path, query and fragment' => ['https://example.test/path?x=1&y=2#top', 'https://example.test/path?x=1&y=2#top'],
            'https with a port' => ['https://example.test:8443/', 'https://example.test:8443/'],
            'https with a subdomain' => ['https://discord.gg/abc123', 'https://discord.gg/abc123'],
            'https with an internationalised host' => ['https://exämple.test/', 'https://exämple.test/'],
            'https with an IPv6 host' => ['https://[2001:db8::1]/', 'https://[2001:db8::1]/'],
            'surrounding spaces are trimmed' => ["  https://example.test/a \n", 'https://example.test/a'],
            'the scheme is lower-cased' => ['HTTPS://Example.test/A', 'https://Example.test/A'],
            'mailto' => ['mailto:info@example.test', 'mailto:info@example.test'],
            'mailto with a subject' => ['mailto:info@example.test?subject=Hola%20socios', 'mailto:info@example.test?subject=Hola%20socios'],
            'mailto in capitals' => ['MAILTO:info@example.test', 'mailto:info@example.test'],
            'tel with an international prefix' => ['tel:+34 600 123 456', 'tel:+34600123456'],
            'tel with punctuation' => ['tel:(91) 123-45.67', 'tel:911234567'],
        ];
    }

    #[DataProvider('acceptedProvider')]
    public function test_it_accepts_https_mailto_and_tel(string $input, string $expected): void
    {
        $url = LinkUrl::from($input);

        $this->assertSame($expected, $url->value);
        $this->assertSame($expected, (string) $url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript in capitals' => ['JAVASCRIPT:alert(1)'],
            'javascript in mixed case with spaces' => ['  JaVaScRiPt:alert(1)'],
            'javascript split by a tab' => ["java\tscript:alert(1)"],
            'javascript split by a newline' => ["java\nscript:alert(1)"],
            'javascript with a leading control character' => ["\x01javascript:alert(1)"],
            'javascript behind a no-break space' => ["\u{00A0}javascript:alert(1)"],
            'javascript with a zero-width space' => ["java\u{200B}script:alert(1)"],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'data in capitals' => ['DATA:text/html;base64,PHNjcmlwdD4='],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
            'plain http' => ['http://example.test/'],
            'ftp' => ['ftp://example.test/'],
            'protocol-relative' => ['//example.test/path'],
            'no scheme' => ['example.test/path'],
            'a relative path' => ['/admin'],
            'empty' => [''],
            'only spaces' => ['   '],
            'https without a host' => ['https://'],
            'https with an empty host' => ['https:///path'],
            'https with credentials' => ['https://user:secret@example.test/'],
            'https with a space in the host' => ['https://exa mple.test/'],
            'https with a space in the path' => ['https://example.test/a b'],
            'https with an angle bracket' => ['https://example.test/<script>'],
            'https with a quote' => ['https://example.test/"onmouseover="x'],
            'https with a backslash' => ['https://example.test\\@evil.test/'],
            'https with a NUL' => ["https://example.test/\x00"],
            'https with a bidirectional override' => ["https://example.test/\u{202E}txt"],
            'https with an invalid port' => ['https://example.test:99999/'],
            'https with a host starting with a dash' => ['https://-example.test/'],
            'mailto without an address' => ['mailto:'],
            'mailto with a wrong address' => ['mailto:not-an-email'],
            'mailto with a script in the query' => ['mailto:info@example.test?body=<script>'],
            'mailto with two addresses' => ['mailto:a@example.test,b@example.test'],
            'tel with letters' => ['tel:abc'],
            'tel with only a plus' => ['tel:+'],
            'tel too short' => ['tel:12'],
            'tel with a script' => ['tel:+34600123456;javascript:alert(1)'],
        ];
    }

    #[DataProvider('rejectedProvider')]
    public function test_it_rejects_every_other_scheme_and_malformed_address(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        LinkUrl::from($input);
    }

    #[DataProvider('rejectedProvider')]
    public function test_try_from_gives_null_for_a_rejected_address(string $input): void
    {
        $this->assertNull(LinkUrl::tryFrom($input));
    }

    public function test_an_address_over_the_limit_is_rejected_and_one_at_the_limit_is_not(): void
    {
        $prefix = 'https://example.test/';

        $this->assertNotNull(LinkUrl::tryFrom($prefix.str_repeat('a', LinkUrl::MAX_LENGTH - strlen($prefix))));
        $this->assertNull(LinkUrl::tryFrom($prefix.str_repeat('a', LinkUrl::MAX_LENGTH - strlen($prefix) + 1)));
    }

    public function test_try_from_gives_null_for_null(): void
    {
        $this->assertNull(LinkUrl::tryFrom(null));
    }

    public function test_only_https_is_external(): void
    {
        $this->assertTrue(LinkUrl::from('https://example.test/')->isExternal());
        $this->assertFalse(LinkUrl::from('mailto:info@example.test')->isExternal());
        $this->assertFalse(LinkUrl::from('tel:+34600123456')->isExternal());
    }

    public function test_it_tells_its_scheme(): void
    {
        $this->assertSame('https', LinkUrl::from('HTTPS://example.test/')->scheme());
        $this->assertSame('mailto', LinkUrl::from('mailto:info@example.test')->scheme());
        $this->assertSame('tel', LinkUrl::from('tel:+34600123456')->scheme());
    }
}
