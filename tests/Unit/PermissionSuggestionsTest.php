<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit;

use App\Application\Modules\DTOs\PermissionDTO;
use Modules\LinkPage\LinkPageServiceProvider;
use Tests\TestCase;

final class PermissionSuggestionsTest extends TestCase
{
    /**
     * editor is the core's content role: the module's permissions go to the roles each
     * association creates. Only reading the statistics is suggested for editor, like the core's
     * search.view_stats; a suggestion is never granted until an admin ticks it.
     */
    public function test_only_reading_the_statistics_is_suggested_to_the_content_role(): void
    {
        $suggested = array_filter(
            (new LinkPageServiceProvider($this->app))->registerPermissions(),
            fn (PermissionDTO $permission): bool => in_array('editor', $permission->roles, true),
        );

        $this->assertSame(['view_stats'], array_values(array_map(fn (PermissionDTO $permission): string => $permission->name, $suggested)));
    }
}
