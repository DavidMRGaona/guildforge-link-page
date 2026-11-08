<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Support;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\LinkModel;

trait CreatesLinks
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function link(array $attributes = []): LinkModel
    {
        return LinkModel::query()->create(array_merge([
            'title' => 'Join our Discord',
            'url' => 'https://discord.gg/example',
        ], $attributes));
    }

    /**
     * An account with panel access and these permissions only.
     *
     * @param  list<string>  $permissionKeys
     */
    private function userWithLinkPermissions(array $permissionKeys): UserModel
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
