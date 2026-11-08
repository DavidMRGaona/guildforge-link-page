<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use InvalidArgumentException;
use Modules\LinkPage\Domain\Enums\BackgroundType;
use Modules\LinkPage\Domain\Enums\LogoSource;
use Modules\LinkPage\Domain\ValueObjects\Appearance;
use Modules\LinkPage\Domain\ValueObjects\ContrastCheck;

/**
 * Between the appearance section of the settings form and the Appearance value object.
 */
final class AppearanceFormState
{
    /**
     * The form state under "appearance". Hidden fields (a colour set the theme does not show,
     * the colours while the site ones are used) are not sent back, so the stored values are
     * the base and the form wins where it has a value.
     *
     * @param  array<array-key, mixed>  $state
     *
     * @throws InvalidArgumentException when a value is outside its list
     */
    public static function toAppearance(array $state, Appearance $stored): Appearance
    {
        $values = array_replace_recursive($stored->toArray(), $state);

        // An image the appearance no longer shows is dropped (and deleted once saved), so no
        // file is left behind on the disk
        if (($values['logo']['source'] ?? null) !== LogoSource::Custom->value) {
            $values['logo']['image'] = null;
        }
        if (($values['background']['type'] ?? null) !== BackgroundType::Image->value) {
            $values['background']['image'] = null;
        }

        return Appearance::fromArray($values);
    }

    /**
     * One message per colour field below WCAG AA, keyed by its Livewire path
     * (data.appearance.<set>.<field>); several pairs on one field are joined.
     *
     * @return array<string, string>
     */
    public static function contrastErrors(Appearance $appearance): array
    {
        $errors = [];

        foreach ($appearance->contrastFailures() as $set => $failures) {
            foreach ($failures as $failure) {
                $key = "data.appearance.{$set}.{$failure->pair->field()}";
                $message = self::message($set, $failure);
                $errors[$key] = isset($errors[$key]) ? $errors[$key].' '.$message : $message;
            }
        }

        return $errors;
    }

    /** A ratio as people read it: one decimal, rounded down so 4.46 never shows as 4,5. */
    public static function ratio(float $ratio): string
    {
        $value = floor($ratio * 10) / 10;

        return Number::format($value, precision: 1, locale: app()->getLocale()) ?: number_format($value, 1);
    }

    /**
     * Deletes the images the new appearance no longer uses (replaced or removed) from the
     * images disk. Only paths under link-page/ ever reach here (Appearance checks them).
     */
    public static function deleteReplacedImages(Appearance $before, Appearance $after): void
    {
        foreach ([[$before->logoImage, $after->logoImage], [$before->backgroundImage, $after->backgroundImage]] as [$old, $new]) {
            if ($old !== null && $old !== $new && ! in_array($old, [$after->logoImage, $after->backgroundImage], true)) {
                Storage::disk('images')->delete($old);
            }
        }
    }

    private static function message(string $set, ContrastCheck $failure): string
    {
        return __('link-page::messages.appearance.contrast.error', [
            'set' => __("link-page::messages.appearance.contrast.sets.{$set}"),
            'pair' => __("link-page::messages.appearance.contrast.pairs.{$failure->pair->value}"),
            'ratio' => self::ratio($failure->ratio),
            'minimum' => self::ratio($failure->minimum),
        ]);
    }
}
