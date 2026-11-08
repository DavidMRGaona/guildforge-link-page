<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Application\Services\EventQueryServiceInterface;
use Illuminate\Support\Str;
use Modules\LinkPage\Application\Contracts\StatsLabelsInterface;
use Modules\LinkPage\Domain\Enums\ClickTargetType;
use Modules\LinkPage\Domain\ValueObjects\ClickTarget;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\LinkModel;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;
use Throwable;

final readonly class StatsLabels implements StatsLabelsInterface
{
    public function __construct(
        private EventQueryServiceInterface $events,
        private CalendarAggregatorServiceInterface $calendar,
    ) {}

    public function qrCodes(array $qrCodeIds): array
    {
        $ids = array_values(array_filter($qrCodeIds, static fn (string $id): bool => Str::isUuid($id)));
        $codes = QrCodeModel::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');

        $labels = [];
        foreach ($qrCodeIds as $id) {
            $code = $codes->get($id);
            $labels[$id] = match (true) {
                ! $code instanceof QrCodeModel => ['name' => __('link-page::messages.stats.deleted_qr'), 'state' => 'deleted'],
                $code->trashed() => ['name' => $code->name, 'state' => 'deleted'],
                ! $code->is_active => ['name' => $code->name, 'state' => 'inactive'],
                default => ['name' => $code->name, 'state' => 'active'],
            };
        }

        return $labels;
    }

    public function targets(array $targets): array
    {
        $linkIds = [];
        foreach ($targets as $target) {
            if ($target->type === ClickTargetType::Link) {
                $linkIds[] = $target->reference;
            }
        }
        $links = $linkIds === [] ? collect() : LinkModel::query()->whereIn('id', $linkIds)->pluck('title', 'id');

        $labels = [];
        foreach ($targets as $target) {
            $labels[$target->value()] = match ($target->type) {
                ClickTargetType::Link => (string) ($links[$target->reference] ?? __('link-page::messages.stats.deleted_link')),
                ClickTargetType::Social => $target->network()?->label() ?? $target->reference,
                ClickTargetType::Event => $this->eventTitle($target->reference),
                ClickTargetType::Calendar => $this->calendarTitle($target),
                ClickTargetType::Home => __('link-page::messages.stats.home'),
            };
        }

        return $labels;
    }

    private function eventTitle(string $id): string
    {
        try {
            return $this->events->findById($id)->title ?? __('link-page::messages.stats.past_event');
        } catch (Throwable) {
            return __('link-page::messages.stats.past_event');
        }
    }

    private function calendarTitle(ClickTarget $target): string
    {
        [$source, $id] = $target->calendarEntry() ?? ['', ''];

        // The aggregator answers null for a source that fails or no longer shows the entry
        return $this->calendar->findEntry($source, $id)->title ?? __('link-page::messages.stats.calendar_entry');
    }
}
