<?php

declare(strict_types=1);

return [
    'title' => 'Página de enlaces',

    'permissions' => [
        'manage' => 'Configurar la página de enlaces',
    ],

    'calendar_views' => [
        'list' => 'Lista',
        'month' => 'Mes',
    ],

    'settings' => [
        'title' => 'Página de enlaces',
        'saved' => 'Página de enlaces guardada',
        'days' => 'días',
        'sections' => [
            'page' => 'Página',
            'events' => 'Próximos eventos',
            'events_help' => 'Botones con los eventos publicados que van a empezar, del más cercano al más lejano. Los fijados van siempre arriba mientras no hayan terminado.',
            'calendar' => 'Calendario',
        ],
        'enabled' => 'Página activa',
        'enabled_help' => 'Apagada, la dirección /enlaces responde «Página no encontrada».',
        'intro' => 'Texto de presentación',
        'intro_help' => 'Se muestra debajo del nombre del sitio.',
        'events_limit' => 'Máximo de eventos',
        'events_limit_help' => 'Botones en total, contando los fijados.',
        'events_within_days' => 'Horizonte',
        'events_within_days_help' => 'Solo los eventos que empiezan dentro de estos días, contando hoy.',
        'events_tags' => 'Etiquetas',
        'events_tags_help' => 'Solo los eventos con alguna de estas etiquetas. Vacío, todos.',
        'events_pinned' => 'Eventos fijados',
        'events_pinned_help' => 'Siempre arriba mientras no hayan terminado, aunque estén fuera del horizonte o de las etiquetas.',
        'events_hidden' => 'Eventos escondidos',
        'events_hidden_help' => 'No se muestran nunca, aunque estén fijados.',
        'calendar_initial_view' => 'Vista inicial',
        'calendar_initial_view_help' => 'La lista se lee mejor en el móvil; desde la página se puede pasar a la otra vista.',
    ],
];
