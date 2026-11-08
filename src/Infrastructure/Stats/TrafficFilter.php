<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Stats;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Modules\LinkPage\Domain\Enums\StatKind;

/**
 * Decides whether a request counts, so the numbers stay close to what people did: automated
 * agents and prefetches never count, the same thing from the same device within a few seconds
 * counts once (double taps, a scanner app that opens the code twice), and one device scanning
 * over and over stops counting.
 *
 * To recognise "the same device" it keeps, in the cache only and for at most a minute, a keyed
 * hash of the address and the agent. Neither the hash nor its parts ever reach the statistics.
 */
final readonly class TrafficFilter
{
    /** Scans counted per device and minute; more are still redirected, only not counted. */
    public const int SCANS_PER_MINUTE = 10;

    /** Requests to the beacon endpoint per device and minute; more answer 429. */
    public const int BEACONS_PER_MINUTE = 30;

    /** Seconds in which the same thing from the same device counts once. */
    public const array REPEAT_WINDOW = [
        'visit' => 30,
        'scan' => 30,
        'click' => 10,
    ];

    private const string KEY_PREFIX = 'link-page:stats:';

    public function __construct(
        private CacheRepository $cache,
        private RateLimiter $limiter,
    ) {}

    public function shouldCount(Request $request, StatKind $kind, string $subject): bool
    {
        if ($this->isAutomated($request)) {
            return false;
        }

        $device = self::device($request);

        if ($kind === StatKind::Scan && ! $this->limiter->attempt(self::KEY_PREFIX.'scans:'.$device, self::SCANS_PER_MINUTE, static fn (): bool => true, 60)) {
            return false;
        }

        // add() only writes when the key is missing, atomically: the first of two simultaneous
        // requests counts and the second does not
        return $this->cache->add(
            self::KEY_PREFIX.'seen:'.hash('sha256', $device.'|'.$kind->value.'|'.$subject),
            true,
            self::REPEAT_WINDOW[$kind->value],
        );
    }

    public function isAutomated(Request $request): bool
    {
        if (BotUserAgents::matches((string) $request->userAgent())) {
            return true;
        }

        // Browsers announce speculative loads; the page does too before its beacon, this is the
        // same rule for the scan redirect
        $purpose = strtolower($request->header('Sec-Purpose', $request->header('Purpose', '')) ?? '');

        return str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender');
    }

    /**
     * A keyed hash of the address and the agent, for the cache keys of this filter and the
     * beacon's rate limit only.
     */
    public static function device(Request $request): string
    {
        return hash_hmac(
            'sha256',
            ($request->ip() ?? '').'|'.($request->userAgent() ?? ''),
            (string) config('app.key'),
        );
    }
}
