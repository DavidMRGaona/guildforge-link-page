<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\Enums\Concerns;

/**
 * Labels from link-page::messages.<group>.<value> and the options of a select, in case order.
 */
trait HasTranslatedOptions
{
    abstract protected static function translationGroup(): string;

    public function label(): string
    {
        return __('link-page::messages.'.self::translationGroup().'.'.$this->value);
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
