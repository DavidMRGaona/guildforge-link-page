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
    // The page as the site paints it; the colour sets are the Minimal template, used once a
    // template other than the site one is picked. Same values as Appearance::defaults().
    'appearance' => [
        'template' => 'site',
        'theme' => 'device',
        'logo' => ['source' => 'site', 'image' => null, 'shape' => 'circle'],
        'font' => 'site_body',
        'buttons' => ['style' => 'outline', 'shape' => 'rounded'],
        'background' => ['type' => 'solid', 'image' => null, 'angle' => 180],
        'light' => [
            'background' => '#FFFFFF',
            'background_to' => '#F2F2F2',
            'text' => '#111111',
            'button' => '#111111',
            'button_text' => '#FFFFFF',
            'overlay' => 60,
        ],
        'dark' => [
            'background' => '#0A0A0A',
            'background_to' => '#1C1C1C',
            'text' => '#F5F5F5',
            'button' => '#F5F5F5',
            'button_text' => '#0A0A0A',
            'overlay' => 60,
        ],
    ],
];
