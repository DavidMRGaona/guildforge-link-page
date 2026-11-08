<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Modules\LinkPage\Application\Contracts\CalendarClickTargetsInterface;
use Modules\LinkPage\Domain\ValueObjects\ClickTarget;
use Throwable;

/**
 * Matches the address against the entries of the public calendar, kept in the cache for a few
 * minutes so a busy page does not rebuild the calendar on every click. Only the path counts,
 * and only on the site's own host.
 */
final readonly class CoreCalendarClickTargets implements CalendarClickTargetsInterface
{
    public const int MONTHS_BACK = 3;

    public const int MONTHS_AHEAD = 12;

    private const string CACHE_KEY = 'link-page:stats:calendar-targets';

    private const int CACHE_SECONDS = 600;

    public function __construct(
        private CalendarAggregatorServiceInterface $calendar,
        private CacheRepository $cache,
    ) {}

    public function forUrl(string $url): ?ClickTarget
    {
        $path = $this->sitePath($url);
        if ($path === null) {
            return null;
        }

        $value = $this->targets()[$path] ?? null;

        return is_string($value) ? ClickTarget::tryFrom($value) : null;
    }

    /**
     * @return array<string, string> path => click target id
     */
    private function targets(): array
    {
        $targets = $this->cache->get(self::CACHE_KEY);
        if (is_array($targets)) {
            /** @var array<string, string> $targets */
            return $targets;
        }

        try {
            $now = CarbonImmutable::now();
            $entries = $this->calendar->findByDateRange(
                $now->subMonths(self::MONTHS_BACK)->toDateTimeImmutable(),
                $now->addMonths(self::MONTHS_AHEAD)->toDateTimeImmutable(),
            );
        } catch (Throwable $exception) {
            // Without the calendar the click is not counted; the link itself still works
            report($exception);

            return [];
        }

        $targets = [];
        foreach ($entries as $entry) {
            $this->add($targets, $entry);
        }

        $this->cache->put(self::CACHE_KEY, $targets, self::CACHE_SECONDS);

        return $targets;
    }

    /**
     * @param  array<string, string>  $targets
     */
    private function add(array &$targets, CalendarEntryDTO $entry): void
    {
        $path = $this->sitePath($entry->url);
        $target = ClickTarget::tryCalendar($entry->sourceType, $entry->id);

        // The first entry keeps an address several entries share (a repeating activity)
        if ($path !== null && $target instanceof ClickTarget && ! isset($targets[$path])) {
            $targets[$path] = $target->value();
        }
    }

    /**
     * The path of an address of this site, without trailing slash, query or fragment; null
     * for another host or anything that is not a web address.
     */
    private function sitePath(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if ($parts === false || ! isset($parts['path'])) {
            return null;
        }

        if (isset($parts['host'])) {
            $siteHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $scheme = strtolower($parts['scheme'] ?? '');
            if (! in_array($scheme, ['http', 'https'], true) || ! is_string($siteHost) || strcasecmp($parts['host'], $siteHost) !== 0) {
                return null;
            }
        } elseif (! str_starts_with($parts['path'], '/')) {
            return null;
        }

        $path = rtrim($parts['path'], '/');

        return $path === '' ? '/' : $path;
    }
}
