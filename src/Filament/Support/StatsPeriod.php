<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Support;

/**
 * The periods the statistics page offers, in days counting today. The filter lives in the URL,
 * so only these values are ever read from it.
 */
final class StatsPeriod
{
    public const int DEFAULT = 30;

    public const array OPTIONS = [7, 30, 90, 365];

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function fromFilters(?array $filters): int
    {
        $days = $filters['days'] ?? null;

        if (is_numeric($days) && in_array((int) $days, self::OPTIONS, true)) {
            return (int) $days;
        }

        return self::DEFAULT;
    }

    /**
     * @return array<int, string> by days
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::OPTIONS as $days) {
            $label = __('link-page::messages.stats.periods.'.$days);
            $options[$days] = is_string($label) ? $label : (string) $days;
        }

        return $options;
    }
}
