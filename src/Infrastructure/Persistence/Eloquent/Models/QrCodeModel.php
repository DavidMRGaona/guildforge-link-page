<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\LinkPage\Application\DTOs\QrCodeDesignDTO;
use Modules\LinkPage\Domain\Enums\QrLogoSource;
use Modules\LinkPage\Domain\ValueObjects\QrLogoPath;
use Modules\LinkPage\Infrastructure\Services\ScanCodeGenerator;
use Throwable;

/**
 * One printed origin of the link page (venue poster, table cards, open days...). Deleting
 * one is a soft delete with no way back: the code stays taken and keeps leading to the page.
 *
 * @property string $id
 * @property string $name
 * @property string $code
 * @property bool $is_active
 * @property string $foreground_color
 * @property string $background_color
 * @property int $margin
 * @property QrLogoSource $logo_source
 * @property string|null $logo_path
 * @property string|null $tagline
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class QrCodeModel extends Model
{
    use HasUuids;
    use SoftDeletes;
    use StoresInstantsInStorageTimezone;

    public const int DEFAULT_MARGIN = 8;

    public const string LOGO_DIRECTORY = QrLogoPath::DIRECTORY;

    protected $table = 'link_page_qr_codes';

    /** The code is never filled from outside: it is given once, when the row is created. */
    protected $fillable = [
        'name',
        'is_active',
        'foreground_color',
        'background_color',
        'margin',
        'logo_source',
        'logo_path',
        'tagline',
    ];

    protected $attributes = [
        'is_active' => true,
        'foreground_color' => '#000000',
        'background_color' => '#FFFFFF',
        'margin' => self::DEFAULT_MARGIN,
        'logo_source' => 'none',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $qr): void {
            if (! is_string($qr->getAttribute('code')) || $qr->getAttribute('code') === '') {
                $qr->setAttribute('code', app(ScanCodeGenerator::class)->next()->value);
            }
        });

        // Only an uploaded logo of a custom source is kept, never a path to another file
        self::saving(function (self $qr): void {
            if ($qr->logo_source !== QrLogoSource::Custom
                || ! is_string($qr->logo_path) || ! QrLogoPath::isOwn($qr->logo_path)) {
                $qr->logo_path = null;
            }
        });

        self::updated(function (self $qr): void {
            $previous = $qr->getOriginal('logo_path');
            if ($qr->wasChanged('logo_path') && is_string($previous)) {
                self::deleteLogo($previous);
            }
        });

        // Nothing draws a deleted code again, so its logo goes
        self::deleted(function (self $qr): void {
            if (is_string($qr->logo_path)) {
                self::deleteLogo($qr->logo_path);
            }
        });
    }

    public function toDesign(): QrCodeDesignDTO
    {
        return new QrCodeDesignDTO(
            code: $this->code,
            foregroundColor: $this->foreground_color,
            backgroundColor: $this->background_color,
            marginPercent: $this->margin,
            logoSource: $this->logo_source,
            logoPath: $this->logo_path,
        );
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'margin' => 'integer',
            'logo_source' => QrLogoSource::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    private static function deleteLogo(string $path): void
    {
        if (! QrLogoPath::isOwn($path)) {
            return;
        }

        try {
            Storage::disk('images')->delete($path);
        } catch (Throwable $exception) {
            // A stale file is not worth failing the save for
            report($exception);
        }
    }
}
