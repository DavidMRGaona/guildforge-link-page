# Página de enlaces

Página de enlaces para el móvil, con los próximos eventos y el calendario

## Qué hace

- **`/enlaces`**: una sola página por sitio, aparte del diseño de la web (sin cabecera, menú ni pie) y pensada para el móvil, a la que lleva un QR impreso. Muestra el logo y el nombre del sitio, un texto de presentación, botones con los próximos eventos del core y el calendario completo del sitio (lista o mes). Al final, un enlace a la web completa. Desactivada, responde 404.
- **Próximos eventos**: solo eventos del core publicados, del más cercano al más lejano, con un máximo (5), un horizonte en días (60) y etiquetas opcionales. Los eventos fijados van arriba mientras no hayan terminado y cuentan dentro del máximo; los escondidos no salen nunca.
- **Enlaces propios**: botones con el título, la dirección, una descripción corta y un icono opcionales (Discord, el formulario de socios…), gestionados en *Contenido → Enlaces propios* con el permiso «Gestionar los enlaces propios de la página» (`link-page:links.manage`).
  - Se ordenan arrastrando las filas y cada uno va **antes** o **después** de los botones de eventos; con enlaces a los dos lados quedan mezclados por orden.
  - **Destacado**: más grande, con insignia y un anillo suave que respira; con `prefers-reduced-motion` el anillo queda quieto.
  - **Programación**: fecha y hora de inicio y fin opcionales, en la zona de visualización del sitio (el fin es exclusivo); fuera de ese rango no se muestra. Una tabla de estado dice si cada enlace está visible, programado, caducado u oculto.
  - **Solo `https`, `mailto:` y `tel:`**, comprobado en el servidor al guardar, al guardar en base de datos y otra vez al servir la página: `javascript:`, `data:` y similares se rechazan también en mayúsculas, con espacios o con caracteres invisibles. Los enlaces `https` se abren en una pestaña nueva con `rel="noopener noreferrer"`.
  - Iconos: los de las redes y medios de contacto y 9 pictogramas genéricos (web, enlace, calendario, grupo, libro, dado, entrada, ubicación y formulario), como SVG dentro del módulo (nada se carga de fuera). Borrar un enlace pide marcar una casilla además de confirmar.
- **Redes sociales**: una fila de iconos debajo del nombre o al final de la página. Las direcciones son las del sitio, que las guarda una sola vez para todos (*Páginas → Nosotros*, pestañas «Redes sociales» y «Contacto»): Instagram, Discord, Telegram, WhatsApp, YouTube, Twitch, X, Facebook, TikTok, Bluesky, correo y teléfono. En *Contenido → Página de enlaces* solo se marca cuáles se muestran (todas las que tenga el sitio, mientras no se guarde otra cosa). Una red sin dirección en el sitio no se puede marcar y, si el sitio la borra, el icono desaparece aunque siga marcada. WhatsApp, YouTube y Twitch aparecen en cuanto el core tenga esos campos (`social_whatsapp`, `social_youtube`, `social_twitch`).
- **Aviso temporal**: una línea arriba del todo para lo puntual («Este sábado el local está cerrado»), en *Contenido → Página de enlaces → Aviso temporal*.
  - Texto de hasta 160 caracteres, enlace opcional (solo `https://` o una ruta del sitio como `/eventos`; cualquier otra cosa se rechaza al guardar y al leer) y un tono: **información**, **aviso** o **importante**.
  - Los tonos se distinguen por el icono, el borde y la palabra que antecede al texto, nunca solo por el color, y usan pares de colores que la página ya valida (contraste AA en claro, oscuro y con colores propios): el fondo suave con el texto, o el color del botón con su texto.
  - Inicio y fin opcionales, escritos en la zona de visualización del sitio y guardados como instantes (ISO 8601); se muestra desde el inicio (inclusive) hasta el fin (exclusivo). Con el interruptor apagado se guarda sin mostrarse.
  - Es un `role="status"`: los lectores de pantalla lo leen con la página, sin interrumpir.
- **Evento destacado**: un evento en grande bajo el logo (antes de los enlaces propios y de «Próximos eventos»), con imagen, fecha, lugar y cuenta atrás. Solo eventos publicados del core. Se elige en *Evento destacado*:
  - **Automático**: el que empieza antes entre los que no han terminado (uno en curso sigue siendo el destacado hasta que acaba), con una etiqueta opcional; los eventos escondidos de la lista no se destacan. Al terminar, pasa al siguiente.
  - **Elegido a mano**: el evento que se elija; al terminar, el bloque desaparece.
  - El evento destacado no se repite en «Próximos eventos».
  - **Cuenta atrás** hasta el inicio (`2 d 5 h`, `3 h 20 min`, y con segundos solo en la última hora). El día del evento, antes de empezar, aparece la etiqueta «Hoy»; al empezar, «Ahora mismo» (con «Hasta el …» si dura varios días). Los eventos de varios días enseñan el rango («11–13 nov»).
  - **Fechas**: el servidor decide qué día es «hoy» en la zona de visualización y manda tres instantes (inicio del día del evento, inicio y fin) más su propia hora; el navegador solo cuenta desde esa hora, así que un móvil con el reloj o la zona mal puesta cuenta igual. Al terminar el evento la página pide al servidor el siguiente (recarga parcial de `featured`, `events` y `serverNow`).
  - **Accesibilidad**: los números que cambian van ocultos a los lectores de pantalla (la fecha va en texto); «Hoy» y «Ahora mismo» están en una región `role="status"` que se anuncia una vez al cambiar. Los segundos no se muestran ni se actualizan con `prefers-reduced-motion` (se actualiza con el minuto).
  - Una vez instalado, viene **apagado**: se activa en el panel.
- **Accesibilidad**: cada botón tiene nombre accesible (el icono es decorativo y los enlaces que abren pestaña nueva lo dicen), foco visible y un área táctil de al menos 44 px.
- **Identificadores estables** para las estadísticas: cada elemento clicable de la página lleva `data-link-id` con un valor que no cambia al editar ni reordenar: `link:<uuid>` (enlace propio), `social:<red>` (icono de red), `event:<id>` (evento), `featured:<id>` (evento destacado), `notice` (enlace del aviso) y `home` (enlace a la web). Las props de Inertia los traen como `linkId`.
- **Apariencia**: por defecto la página sigue la paleta del sitio en vivo. Las plantillas (Pergamino, Taberna, Arcano, Bosque y Minimal) rellenan los campos, que luego se pueden cambiar («Personalizados»):
  - Logo (el del sitio, una imagen propia o la inicial) en círculo o cuadrado redondeado.
  - Un juego de colores para el tema claro y otro para el oscuro: fondo, texto, botón y texto del botón.
  - Fondo liso, degradado (dos colores y ángulo) o imagen con oscurecido.
  - Botones de relleno, contorno o sombra, con esquinas rectas, redondeadas o en píldora.
  - Letra: las del sitio, Inter, Playfair Display o las del dispositivo, sin descargar nada de fuera.
  - Tema según el dispositivo, siempre claro o siempre oscuro.

  El servidor exige contraste AA en cada tema que se usa: 4,5:1 para el texto sobre el fondo (con un degradado, sobre los dos colores; con una imagen, sobre el píxel más claro y el más oscuro bajo el oscurecido) y para el texto del botón; 3:1 para el botón contra el fondo, o 4,5:1 si el botón es de contorno. Si no llega, no se guarda y el campo dice qué par falla. El formulario tiene una vista previa en directo. Las imágenes van al disco `images` (`link-page/AAAA/MM`), y la anterior se borra al cambiarla o quitarla. No hay CSS libre: todo sale de listas cerradas y colores `#RRGGBB`.
- **Compartir**: la página se comparte mucho por WhatsApp, Telegram y redes.
  - **Tarjeta** (Open Graph y Twitter Card): título, descripción e imagen, con vista previa en directo en *Contenido → Página de enlaces → Compartir*. Lo que se deja vacío sale del sitio: el nombre, el texto de presentación (y, sin él, la descripción del sitio) y el logo. Sale en el HTML del servidor, con las metas del core (`SeoMetadataFactory`), porque los rastreadores de esas apps no ejecutan JavaScript. La imagen propia (JPG o PNG, hasta 1 MB, recomendada de 1200 × 630 px) va al disco `images` (`link-page/share/AAAA/MM`); el core la sirve recortada desde el centro a ese tamaño, y la anterior se borra al cambiarla o quitarla. Solo se usan archivos que el propio formulario guardó.
  - **Botón «Compartir»** en la esquina superior derecha de la página: abre el menú del móvil (Web Share API) con el título, la descripción y la dirección de `/enlaces`; si el navegador no lo tiene, copia la dirección y lo avisa en una región `aria-live`. La dirección es siempre la de la página, nunca la del QR ni la que llevaba parámetros.
  - **No indexar en buscadores**: opcional y desactivado por defecto; añade `noindex, nofollow` a la página. No impide entrar con el enlace o el QR.
- **Suscribirse al calendario**: botón «Suscribirse» al final de la sección del calendario, para quien llega por el QR y casi nunca tiene cuenta. Despliega debajo (sin menú flotante, para que no se corte en pantallas estrechas) cuatro opciones: abrir la dirección `webcal://` en la app de calendario (Apple Calendar, Outlook de escritorio…), añadirla a Google Calendar, añadirla a Outlook en la web y copiar la dirección (la `https`, que sirve para pegarla en cualquier servicio). Las direcciones las construye el core (`PublicCalendarFeedInterface::subscriptionLinks()`, feed público `/calendario.ics` del core ≥ 3.40): el módulo nunca las arma. Es de solo lectura y sin sesión. Los botones usan los colores, la forma y el anillo de foco de la apariencia, miden al menos 48 px de alto, y al copiar un aviso con `aria-live="polite"` dice «Dirección copiada. Pégala en tu app de calendario.»; si el navegador no deja copiar, se muestra la dirección ya seleccionada. En iPhone con Chrome, Firefox o Edge (que no abren `webcal://`) sale una línea que sugiere usar Safari o copiar. El componente `CalendarSubscribe.vue` tiene un hueco (`actions`) junto al botón, para la suscripción personal de quien tenga sesión.
- **Panel**: *Contenido → Página de enlaces*, con el permiso «Configurar la página de enlaces» (`link-page:manage`). Los ajustes se guardan en la tabla `settings` del core y sobreviven a las actualizaciones.
- **Códigos QR** (*Contenido → Códigos QR*, permiso «Gestionar e imprimir los códigos QR», `link-page:qr_codes.manage`): uno por cada sitio donde va impreso (cartel del local, mesas, tarjetas, jornadas…), con su nombre y un código fijo de seis caracteres.
  - El QR lleva a **`/qr/{código}`**, que redirige a `/enlaces` (a la portada si la página está apagada). Ese destino es fijo, nunca sale de la petición. Un código desactivado o borrado sigue llevando a la página, así que un cartel impreso no da error nunca; solo un código desconocido responde 404.
  - **Borrar un QR** pide doble confirmación y no tiene vuelta atrás, pero su código queda reservado para siempre: nunca se reutiliza.
  - **Diseño**: colores del código y del fondo, margen y logo en el centro (ninguno, el del sitio o una imagen propia), con vista previa en directo. Se dibuja con el generador de QR del core: si el contraste no permite leerlo con el móvil, no se guarda.
  - **Descarga** en SVG o PNG de 256, 512, 1024 o 2048 px, y una **hoja para imprimir** en A4 o A5 con el nombre del sitio, el QR, su frase («Escanea para ver los próximos eventos» por defecto) y la dirección corta. El navegador la imprime o la guarda como PDF.
  - Los escaneos de los QR activos se cuentan en las estadísticas (`QrScanRecorderInterface`); los desactivados y borrados no suman.
- **Estadísticas anónimas**: contadores por día (zona de visualización) de visitas de `/enlaces`, escaneos por código QR y clics por elemento, en la tabla `link_page_stats` (tipo, elemento, día y cuenta). Nunca se guarda la IP, la cuenta, el agente de usuario ni cookies, así que no necesitan aviso de cookies.
  - **Cómo llegan**: el escaneo se cuenta en el servidor, en `/qr/{código}`. La visita y los clics los manda la página con `navigator.sendBeacon` a `POST /api/link-page/stats`, una ruta `api` sin sesión ni cookies (composable `useLinkPageStats`). Los enlaces van directos a su destino, sin redirección intermedia, así que funcionan aunque el aviso falle o lo bloqueen. El endpoint solo acepta lo que la página muestra en ese momento (enlaces activos y en fecha, redes con dirección, eventos de la lista, el destacado del momento, el enlace del aviso mientras se ve, `home`, y entradas del calendario reconocidas por su dirección entre los tres meses anteriores y el año siguiente); lo demás responde 422.
  - **Qué no cuenta**: recargas y atrás o adelante (Navigation Timing, sin guardar nada en el navegador), precargas, agentes automáticos (buscadores, vistas previas de WhatsApp, Telegram, Facebook o Discord, monitores, `curl`, navegadores sin interfaz o con `navigator.webdriver`), quien tiene acceso al panel, y la misma cosa desde el mismo dispositivo en pocos segundos (30 s visitas y escaneos, 10 s clics). Para reconocer el dispositivo se usa un hash con clave de la dirección (IPv6 por su /64) y del agente que vive un minuto como mucho en la caché y nunca pasa a la tabla.
  - **Límites**: el endpoint admite 30 peticiones por minuto y dispositivo y 120 por dirección (429 al pasarse). En `/qr/` la redirección nunca se bloquea, pero dejan de contar los escaneos que pasan de 10 por minuto y dispositivo o de 60 por dirección.
  - **Panel** (permiso «Ver las estadísticas de la página de enlaces», `link-page:stats.view`, sugerido para editor): widget del escritorio con los últimos 30 días y *Contenido → Estadísticas de enlaces* con el periodo (7, 30, 90 o 365 días), la evolución por día y los rankings de códigos QR y de enlaces. La guía de ayuda `link-page.panel.stats` explica qué representan las cifras y qué no.
  - **Poda**: `link-page:prune-stats [--days=]`, programada cada noche a las 03:40, borra los días anteriores al plazo de *Contenido → Página de enlaces → Estadísticas* (365 días por defecto, entre 30 y 3650).

## Requisitos

- GuildForge core `^3.40` (feed público del calendario, `PublicCalendarFeedInterface`)
- PHP >= 8.5, Laravel >= 13, Filament 5

El core rechaza el módulo si no se cumplen (`module.json` → `requires`).

## Instalación

1. Descarga `link-page-X.Y.Z.zip` de la última release de `DavidMRGaona/guildforge-link-page` en GitHub.
2. En el panel, activa el modo mantenimiento (**Configuración del sitio** → pestaña **Mantenimiento**).
3. En **Módulos**, pulsa **Instalar / actualizar** y sube el ZIP.
4. Pulsa **Habilitar** en el módulo: la primera vez ejecuta sus migraciones.
5. Desactiva el modo mantenimiento.

Las versiones nuevas se aplican desde **Actualizaciones de módulos** (también con el modo mantenimiento activo) o con `php artisan module:update link-page`.

### En desarrollo

Con el repositorio clonado en `src/modules/link-page`, desde `src/`:

```bash
php artisan module:discover
php artisan module:enable link-page
```

Los componentes Vue se compilan con las herramientas del core:

```bash
docker exec guildforge_node npm run build:module -- link-page
```

En las releases los assets los compila el CI y van dentro del ZIP.

## Tests

Desde `src/` (o dentro del contenedor `guildforge_app`):

```bash
XDEBUG_MODE=off vendor/bin/phpunit -c modules/link-page/phpunit.xml
```

`php artisan test` no ejecuta los tests de los módulos.

Los componentes Vue tienen sus tests en `tests/js` (Vitest, Vue Test Utils y happy-dom) y se ejecutan con las herramientas del core, porque importan sus fuentes con `@/`:

```bash
docker exec guildforge_node sh -c 'cd modules/link-page && ../../node_modules/.bin/vitest run'
```

`tests/js/setup.ts` carga los textos del core y del módulo en castellano y sustituye Inertia (router y enlaces) y el layout del sitio.

## Calidad del código

El módulo pasa las mismas herramientas que el core, con su configuración: Rector, Pint, PHPStan, ESLint, Prettier, `vue-tsc` y Vitest. En cada pull request las ejecuta el job *Quality* de `tests.yml`. Desde la raíz del core:

```bash
make check MODULE=link-page
```

## Publicar una versión

1. Sube `version` en `module.json` (un módulo nuevo empieza en `0.0.0`, sin publicar).
2. Crea y empuja la etiqueta `vX.Y.Z` (debe coincidir con `version`).
3. El workflow `release.yml` ejecuta los tests contra el core (`main`, o la variable `GUILDFORGE_HOST_REF`), compila los assets y publica `link-page-X.Y.Z.zip` con su `.sha256` en la release.
