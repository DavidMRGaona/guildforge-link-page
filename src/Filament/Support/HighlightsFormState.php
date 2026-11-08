<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Support;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use Exception;
use Modules\LinkPage\Domain\Enums\FeaturedMode;
use Modules\LinkPage\Domain\Enums\NoticeTone;
use Modules\LinkPage\Domain\ValueObjects\FeaturedSettings;
use Modules\LinkPage\Domain\ValueObjects\NoticeLink;

/**
 * Between the notice and featured sections of the settings form and the stored `notice` and
 * `featured` settings. The date pickers hand over and take storage-zone wall times
 * (`Y-m-d H:i:s`); the settings keep ISO 8601 instants in the display zone.
 */
final class HighlightsFormState
{
    private const string PICKER_FORMAT = 'Y-m-d H:i:s';

    /**
     * What the form shows for the stored notice.
     *
     * @param  array{enabled: bool, text: string|null, tone: string, link_url: string|null, link_label: string|null, starts_at: string|null, ends_at: string|null}  $stored
     * @return array{enabled: bool, text: string|null, tone: string, link_url: string|null, link_label: string|null, starts_at: string|null, ends_at: string|null}
     */
    public static function noticeToForm(array $stored, TimezoneServiceInterface $timezone): array
    {
        $stored['starts_at'] = self::toPicker($stored['starts_at'], $timezone);
        $stored['ends_at'] = self::toPicker($stored['ends_at'], $timezone);

        return $stored;
    }

    /**
     * What gets stored for the notice: the texts trimmed, the tone from the closed list, the link
     * only with an address (a label alone is dropped) and the dates as instants. Values the
     * fields already validated are not checked again.
     *
     * @param  array<array-key, mixed>  $state
     * @return array{enabled: bool, text: string|null, tone: string, link_url: string|null, link_label: string|null, starts_at: string|null, ends_at: string|null}
     */
    public static function noticeToStored(array $state, TimezoneServiceInterface $timezone): array
    {
        $tone = $state['tone'] ?? null;
        $url = self::text($state['link_url'] ?? null);
        $label = self::text($state['link_label'] ?? null);

        return [
            'enabled' => ($state['enabled'] ?? false) === true,
            'text' => self::text($state['text'] ?? null),
            'tone' => is_string($tone) && NoticeTone::tryFrom($tone) !== null ? $tone : NoticeTone::Info->value,
            'link_url' => $url !== null && NoticeLink::isValidUrl($url) ? $url : null,
            'link_label' => $url !== null && NoticeLink::isValidUrl($url) && $label !== null ? mb_substr($label, 0, NoticeLink::MAX_LABEL_LENGTH) : null,
            'starts_at' => self::fromPicker($state['starts_at'] ?? null, $timezone),
            'ends_at' => self::fromPicker($state['ends_at'] ?? null, $timezone),
        ];
    }

    /**
     * What gets stored for the featured event: the tag only in automatic mode, the event only in
     * manual mode.
     *
     * @param  array<array-key, mixed>  $state
     * @return array{mode: string, tag: string|null, event: string|null}
     */
    public static function featuredToStored(array $state): array
    {
        $featured = FeaturedSettings::fromArray($state);

        return (new FeaturedSettings(
            $featured->mode,
            $featured->mode === FeaturedMode::Auto ? $featured->tagSlug : null,
            $featured->mode === FeaturedMode::Manual ? $featured->eventId : null,
        ))->toArray();
    }

    private static function toPicker(?string $iso, TimezoneServiceInterface $timezone): ?string
    {
        if ($iso === null) {
            return null;
        }

        try {
            return $timezone->toStorage(new DateTimeImmutable($iso))->format(self::PICKER_FORMAT);
        } catch (Exception) {
            return null;
        }
    }

    private static function fromPicker(mixed $state, TimezoneServiceInterface $timezone): ?string
    {
        if (! is_string($state) || trim($state) === '') {
            return null;
        }

        try {
            return $timezone->iso(new DateTimeImmutable(trim($state), $timezone->storageTimezone()));
        } catch (Exception) {
            return null;
        }
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
