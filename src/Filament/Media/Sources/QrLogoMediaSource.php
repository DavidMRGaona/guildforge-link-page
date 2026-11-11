<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Media\Sources;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Infrastructure\Media\Sources\EloquentColumnMediaSource;
use Illuminate\Database\Eloquent\Model;
use Modules\LinkPage\Filament\Resources\QrCodeResource;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;

/**
 * The logos uploaded for QR codes (link-page/qr). QR codes are soft-deleted: one in the trash keeps its logo in use.
 */
final class QrLogoMediaSource extends EloquentColumnMediaSource implements ReleasesMediaThroughCoreInterface
{
    public const string TYPE = 'link-page.qr_logos';

    public function __construct(
        MediaFileIdResolverInterface $resolver,
        private readonly AuthorizationServiceInterface $authorization,
    ) {
        parent::__construct($resolver);
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('link-page::messages.media.sources.qr_logos');
    }

    public function folders(): array
    {
        return ['link-page/qr/'];
    }

    protected function modelClass(): string
    {
        return QrCodeModel::class;
    }

    protected function column(): string
    {
        return 'logo_path';
    }

    protected function role(): string
    {
        return __('link-page::messages.media.roles.qr_logo');
    }

    protected function titleOf(Model $record): string
    {
        return (string) $record->getAttribute('name');
    }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        if (! $this->authorization->can($user, 'link-page:qr_codes.manage')) {
            return null;
        }

        return QrCodeResource::getUrl('edit', ['record' => $record]);
    }
}
