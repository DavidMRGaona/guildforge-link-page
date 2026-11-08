<?php

declare(strict_types=1);

namespace Modules\LinkPage\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;

/**
 * Everything needs link-page:qr_codes.manage. A deleted code is never restored or destroyed:
 * it stays reserved so the posters that carry it keep leading to the page.
 */
final class QrCodePolicy
{
    use AuthorizesWithPermissions;

    public const string MANAGE = 'link-page:qr_codes.manage';

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function view(UserModel $user, QrCodeModel $qrCode): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function update(UserModel $user, QrCodeModel $qrCode): bool
    {
        return ! $qrCode->trashed() && $this->authorize($user, self::MANAGE);
    }

    public function delete(UserModel $user, QrCodeModel $qrCode): bool
    {
        return ! $qrCode->trashed() && $this->authorize($user, self::MANAGE);
    }

    public function restore(UserModel $user, QrCodeModel $qrCode): bool
    {
        return false;
    }

    public function forceDelete(UserModel $user, QrCodeModel $qrCode): bool
    {
        return false;
    }
}
