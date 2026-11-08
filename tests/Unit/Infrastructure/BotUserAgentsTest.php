<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Infrastructure;

use Modules\LinkPage\Infrastructure\Stats\BotUserAgents;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BotUserAgentsTest extends TestCase
{
    #[DataProvider('automated')]
    public function test_crawlers_previews_and_scripts_never_count(string $userAgent): void
    {
        $this->assertTrue(BotUserAgents::matches($userAgent));
    }

    #[DataProvider('people')]
    public function test_phone_and_desktop_browsers_count(string $userAgent): void
    {
        $this->assertFalse(BotUserAgents::matches($userAgent));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function automated(): iterable
    {
        yield 'empty' => [''];
        yield 'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'];
        yield 'Bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'];
        yield 'WhatsApp preview' => ['WhatsApp/2.23.20.0'];
        yield 'Telegram preview' => ['TelegramBot (like TwitterBot)'];
        yield 'Facebook preview' => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'];
        yield 'Discord preview' => ['Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)'];
        yield 'Slack preview' => ['Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)'];
        yield 'Apple preview' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/601.2.4 (KHTML, like Gecko) Version/9.0.1 Safari/601.2.4 facebookexternalhit/1.1 Facebot Twitterbot/1.0'];
        yield 'curl' => ['curl/8.4.0'];
        yield 'python' => ['python-requests/2.31.0'];
        yield 'headless Chrome' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0.0.0 Safari/537.36'];
        yield 'Lighthouse' => ['Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36 Chrome-Lighthouse'];
        yield 'uptime monitor' => ['Mozilla/5.0+(compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function people(): iterable
    {
        yield 'iPhone Safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1'];
        yield 'Android Chrome' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'];
        yield 'desktop Firefox' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0'];
        yield 'Telegram in-app browser' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 Telegram-Android/10.3.2'];
        yield 'Facebook in-app browser' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/440.0.0.33.116]'];
        yield 'Instagram in-app browser' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 307.0.0.34.111'];
    }
}
