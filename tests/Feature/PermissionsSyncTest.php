<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use Illuminate\Support\Facades\Artisan;
use Modules\LinkPage\LinkPageServiceProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The core saves permissions with permissions:sync (also inside module:finish-update). One key
 * it refuses makes the command throw before saving any permission, the core's and every
 * other module's included, so the module's keys must go through the real registry and sync.
 */
final class PermissionsSyncTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    public function test_permissions_sync_saves_every_permission_of_the_module(): void
    {
        $this->assertSame(0, Artisan::call('permissions:sync'));

        $expected = array_map(
            fn ($permission): string => 'link-page:'.$permission->name,
            (new LinkPageServiceProvider($this->app))->registerPermissions(),
        );

        $this->assertNotSame([], $expected);

        foreach ($expected as $key) {
            $this->assertTrue(app(PermissionRegistryInterface::class)->has($key), $key);
            $this->assertTrue(PermissionModel::query()->where('key', $key)->exists(), $key);
        }
    }

    public function test_the_settings_permission_is_stored_under_the_settings_resource(): void
    {
        $this->assertSame(0, Artisan::call('permissions:sync'));

        $permission = PermissionModel::query()->where('key', 'link-page:settings.manage')->first();

        $this->assertNotNull($permission);
        $this->assertSame('settings', $permission->resource);
        $this->assertSame('manage', $permission->action);
        $this->assertSame('link-page', $permission->module);
        $this->assertFalse(PermissionModel::query()->where('key', 'link-page:manage')->exists());
    }
}
