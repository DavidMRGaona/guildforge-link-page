<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Support;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel;

trait CreatesQrCodes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function qrCode(array $attributes = []): QrCodeModel
    {
        return QrCodeModel::query()->create(array_merge(['name' => 'Venue poster'], $attributes));
    }

    /**
     * An account with panel access and these permissions only.
     *
     * @param  list<string>  $permissionKeys
     */
    private function userWith(array $permissionKeys): UserModel
    {
        $role = RoleModel::query()->create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'link-page', 'action' => 'manage', 'module' => 'link-page'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
