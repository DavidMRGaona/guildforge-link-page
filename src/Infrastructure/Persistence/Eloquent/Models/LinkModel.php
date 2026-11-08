<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\LinkPage\Domain\Enums\LinkIcon;
use Modules\LinkPage\Domain\Enums\LinkPlacement;
use Modules\LinkPage\Domain\Enums\LinkVisibility;
use Modules\LinkPage\Domain\ValueObjects\LinkUrl;

/**
 * One own link of the page (Discord, the sign-up form…). Its id never changes, so the
 * statistics can count its clicks whatever is edited or reordered.
 *
 * @property string $id
 * @property string $title
 * @property string $url
 * @property string|null $description
 * @property LinkIcon|null $icon
 * @property LinkPlacement $placement
 * @property bool $is_featured
 * @property bool $is_active
 * @property int $sort_order
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class LinkModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    public const int MAX_TITLE_LENGTH = 100;

    public const int MAX_DESCRIPTION_LENGTH = 160;

    protected $table = 'link_page_links';

    protected $fillable = [
        'title',
        'url',
        'description',
        'icon',
        'placement',
        'is_featured',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected $attributes = [
        'placement' => 'before',
        'is_featured' => false,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        // Whatever writes a link, the address stored is a normalised https, mailto or tel one:
        // an invalid one stops the save instead of reaching a button
        self::saving(function (self $link): void {
            $link->setAttribute('url', LinkUrl::from((string) $link->getAttribute('url'))->value);

            $description = $link->getAttribute('description');
            if (is_string($description)) {
                $link->setAttribute('description', trim($description) === '' ? null : trim($description));
            }
        });

        self::creating(function (self $link): void {
            if (! is_int($link->getAttribute('sort_order')) || $link->getAttribute('sort_order') === 0) {
                $link->setAttribute('sort_order', (int) self::query()->max('sort_order') + 1);
            }
        });
    }

    /**
     * What the statistics and the page mark the link with.
     */
    public function linkId(): string
    {
        return 'link:'.$this->id;
    }

    public function visibilityAt(DateTimeInterface $now): LinkVisibility
    {
        if (! $this->is_active) {
            return LinkVisibility::Hidden;
        }

        if ($this->starts_at !== null && $this->starts_at > $now) {
            return LinkVisibility::Scheduled;
        }

        // The end is exclusive: at that very instant the link is gone
        if ($this->ends_at !== null && $this->ends_at <= $now) {
            return LinkVisibility::Expired;
        }

        return LinkVisibility::Visible;
    }

    protected function casts(): array
    {
        return [
            'icon' => LinkIcon::class,
            'placement' => LinkPlacement::class,
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
