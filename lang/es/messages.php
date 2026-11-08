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

    'templates' => [
        'site' => 'Colores del sitio',
        'parchment' => 'Pergamino',
        'tavern' => 'Taberna',
        'arcane' => 'Arcano',
        'forest' => 'Bosque',
        'minimal' => 'Minimal',
        'custom' => 'Personalizados',
    ],

    'theme_modes' => [
        'device' => 'Según el dispositivo',
        'light' => 'Siempre claro',
        'dark' => 'Siempre oscuro',
    ],

    'background_types' => [
        'solid' => 'Color liso',
        'gradient' => 'Degradado',
        'image' => 'Imagen',
    ],

    'button_styles' => [
        'filled' => 'Relleno',
        'outline' => 'Contorno',
        'shadow' => 'Sombra',
    ],

    'button_shapes' => [
        'square' => 'Rectas',
        'rounded' => 'Redondeadas',
        'pill' => 'Píldora',
    ],

    'logo_sources' => [
        'site' => 'Logo del sitio',
        'custom' => 'Imagen propia',
        'initial' => 'Inicial del nombre',
    ],

    'logo_shapes' => [
        'circle' => 'Círculo',
        'rounded' => 'Cuadrado redondeado',
    ],

    'fonts' => [
        'site_heading' => 'La de los títulos del sitio',
        'site_body' => 'La del texto del sitio',
        'inter' => 'Inter',
        'playfair' => 'Playfair Display',
        'system_sans' => 'Sin serifa del sistema',
        'system_serif' => 'Con serifa del sistema',
        'system_mono' => 'Monoespaciada del sistema',
    ],

    'appearance' => [
        'section' => 'Apariencia',
        'section_help' => 'Cómo se ve la página: colores, fondo, botones y letra. Los colores tienen que leerse bien (contraste AA): si no llegan, no se guardan.',
        'template' => 'Plantilla',
        'template_help' => '«Colores del sitio» sigue la paleta del sitio. Las demás rellenan los campos de abajo, que luego se pueden cambiar.',
        'theme' => 'Tema',
        'theme_help' => 'Según el dispositivo usa el tema que el visitante eligió en la web o, si no eligió, el de su dispositivo.',
        'logo' => 'Logo',
        'logo_source' => 'Imagen de arriba',
        'logo_image' => 'Imagen propia',
        'logo_image_help' => 'PNG, JPG o WebP de hasta 1 MB. Se recorta para llenar la forma.',
        'logo_shape' => 'Forma',
        'font' => 'Letra',
        'font_help' => 'Ninguna se descarga de fuera: son las del sitio, las que ya carga la web o las del dispositivo.',
        'buttons_style' => 'Estilo de los botones',
        'buttons_shape' => 'Esquinas de los botones',
        'background' => 'Fondo',
        'background_type' => 'Tipo de fondo',
        'background_angle' => 'Ángulo del degradado',
        'background_angle_help' => '0° va de abajo arriba; 90°, de izquierda a derecha; 180°, de arriba abajo.',
        'background_image' => 'Imagen de fondo',
        'background_image_help' => 'PNG, JPG o WebP de hasta 4 MB. Se cubre con el color de fondo de cada tema, con el oscurecido que se elija.',
        'degrees' => 'grados',
        'light' => 'Tema claro',
        'dark' => 'Tema oscuro',
        'colors' => [
            'background' => 'Fondo',
            'background_gradient' => 'Fondo (inicio del degradado)',
            'background_image' => 'Color sobre la imagen',
            'background_to' => 'Fin del degradado',
            'text' => 'Texto',
            'button' => 'Botón',
            'button_text' => 'Texto del botón',
            'overlay' => 'Oscurecido de la imagen',
            'overlay_help' => 'Cuánto cubre el color de fondo la imagen. El texto tiene que leerse sobre la parte más clara y la más oscura de cualquier imagen.',
        ],
        'contrast' => [
            'error' => 'Contraste insuficiente en el :set: :pair da :ratio:1 y hace falta :minimum:1.',
            'pairs' => [
                'text' => 'el texto sobre el fondo',
                'button_text' => 'el texto del botón sobre el botón',
                'button_edge' => 'el botón contra el fondo',
                'button_outline' => 'el contorno y el texto del botón sobre el fondo',
            ],
            'sets' => [
                'light' => 'tema claro',
                'dark' => 'tema oscuro',
            ],
        ],
        'preview' => [
            'title' => 'Vista previa',
            'help' => 'Así se verá la página en un móvil. Se actualiza mientras editas; el calendario no se muestra.',
            'site_colors' => 'Con los colores del sitio la vista previa es aproximada: el sitio ya los mantiene legibles.',
            'invalid' => 'Algún valor no es válido todavía: se muestra la última apariencia guardada.',
            'sample_event' => 'Partida de presentación',
            'sample_event_2' => 'Torneo de escaramuzas',
            'upcoming' => 'Próximos eventos',
            'pinned' => 'Destacado',
            'visit_site' => 'Ir a la web de :name',
            'sample_meta' => 'sáb, 14 nov, 18:00 · Sala del club',
            'contrast_title' => 'Contraste',
            'contrast' => [
                'text' => 'Texto sobre el fondo',
                'button_text' => 'Texto del botón',
                'button_edge' => 'Botón contra el fondo',
                'button_outline' => 'Contorno sobre el fondo',
            ],
            'passes' => 'Cumple',
            'fails' => 'No cumple',
        ],
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
