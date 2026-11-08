<?php

declare(strict_types=1);

namespace Modules\LinkPage\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\Contracts\LinkPageStatsRecorderInterface;
use Modules\LinkPage\Application\Services\VisibleClickTargets;
use Modules\LinkPage\Domain\Enums\StatKind;
use Modules\LinkPage\Domain\ValueObjects\ClickTarget;
use Modules\LinkPage\Infrastructure\Stats\TrafficFilter;

/**
 * POST /api/link-page/stats, what the page sends with navigator.sendBeacon: a visit, or a click
 * on something it shows. An API route, so there is no session and no cookie; the links never
 * go through here, they lead straight to their destination.
 */
final readonly class StatsBeaconController
{
    public function __construct(
        private LinkPageSettingsInterface $settings,
        private VisibleClickTargets $targets,
        private TrafficFilter $filter,
        private LinkPageStatsRecorderInterface $stats,
    ) {}

    public function __invoke(Request $request): Response
    {
        abort_unless($this->settings->isEnabled(), 404);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.StatKind::Visit->value.','.StatKind::Click->value],
            'id' => ['required_if:type,'.StatKind::Click->value, 'string', 'max:'.ClickTarget::MAX_LENGTH],
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        if ($data['type'] === StatKind::Visit->value) {
            if ($this->filter->shouldCount($request, StatKind::Visit, '')) {
                $this->stats->recordVisit();
            }

            return response()->noContent();
        }

        $target = $this->targets->resolve((string) $data['id'], isset($data['url']) ? (string) $data['url'] : null);
        if ($target === null) {
            throw ValidationException::withMessages(['id' => __('link-page::messages.stats.unknown_target')]);
        }

        if ($this->filter->shouldCount($request, StatKind::Click, $target->value())) {
            $this->stats->recordClick($target);
        }

        return response()->noContent();
    }
}
