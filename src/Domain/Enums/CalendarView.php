<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums;

/**
 * The views of the site calendar the page offers; the values are the ones the core's
 * ModuleEventCalendar takes.
 */
enum CalendarView: string
{
    case List = 'list';
    case Month = 'month';

    public function label(): string
    {
        return __('link-page::messages.calendar_views.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
