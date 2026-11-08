<?php

declare(strict_types=1);

namespace Modules\LinkPage\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\LinkModel;

/**
 * Everything about the own links needs link-page:links.manage. Admins hold every permission;
 * the settings permission (link-page:settings.manage) does not open this list.
 */
final class LinkPolicy
{
    use AuthorizesWithPermissions;

    public const string MANAGE = 'link-page:links.manage';

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function view(UserModel $user, LinkModel $link): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function update(UserModel $user, LinkModel $link): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function delete(UserModel $user, LinkModel $link): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function reorder(UserModel $user): bool
    {
        return $this->authorize($user, self::MANAGE);
    }
}
