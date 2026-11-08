<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Release defaults of the link page settings
|--------------------------------------------------------------------------
|
| Read-only: what the panel saves goes to the settings table and is merged
| over these values. New keys added by a release keep these defaults.
|
*/

return [
    'enabled' => true,
    'intro' => null,
    'events' => [
        'limit' => 5,
        'within_days' => 60,
        'tags' => [],
        'pinned' => [],
        'hidden' => [],
    ],
    'calendar' => [
        'initial_view' => 'list',
    ],
];
