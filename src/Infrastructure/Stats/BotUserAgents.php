<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Stats;

/**
 * Known automated agents: search crawlers, the link previews chat apps fetch when someone
 * shares the page, monitors, audits and scripts. The agent is only read to decide this,
 * never stored.
 */
final class BotUserAgents
{
    private const string PATTERN = '/bot\b|bot\/|crawl|spider|slurp|scrap|preview|externalhit|facebot|'
        // The preview fetchers only: the in-app browsers of these apps are people
        .'^whatsapp|telegrambot|skypeuripreview|embedly|quora link|vkshare|pinterest|'
        .'headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|statuscake|'
        .'curl|wget|python|httpie|okhttp|go-http|java\/|libwww|axios|node-fetch|guzzle|postman|'
        .'feedfetcher|mediapartners|bingpreview|yandex|baidu|duckduck|petal|semrush|ahrefs|mj12/i';

    public static function matches(string $userAgent): bool
    {
        $userAgent = trim($userAgent);

        return $userAgent === '' || preg_match(self::PATTERN, $userAgent) === 1;
    }
}
