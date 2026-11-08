# Página de enlaces

Página de enlaces para el móvil, con los próximos eventos y el calendario

## Qué hace

- **`/enlaces`**: una sola página por sitio, aparte del diseño de la web (sin cabecera, menú ni pie) y pensada para el móvil, a la que lleva un QR impreso. Muestra el logo y el nombre del sitio, un texto de presentación, botones con los próximos eventos del core y el calendario completo del sitio (lista o mes). Al final, un enlace a la web completa. Desactivada, responde 404.
- **Próximos eventos**: solo eventos del core publicados, del más cercano al más lejano, con un máximo (5), un horizonte en días (60) y etiquetas opcionales. Los eventos fijados van arriba mientras no hayan terminado y cuentan dentro del máximo; los escondidos no salen nunca.
- **Apariencia**: por defecto la página sigue la paleta del sitio en vivo. Las plantillas (Pergamino, Taberna, Arcano, Bosque y Minimal) rellenan los campos, que luego se pueden cambiar («Personalizados»):
  - Logo (el del sitio, una imagen propia o la inicial) en círculo o cuadrado redondeado.
  - Un juego de colores para el tema claro y otro para el oscuro: fondo, texto, botón y texto del botón.
  - Fondo liso, degradado (dos colores y ángulo) o imagen con oscurecido.
  - Botones de relleno, contorno o sombra, con esquinas rectas, redondeadas o en píldora.
  - Letra: las del sitio, Inter, Playfair Display o las del dispositivo, sin descargar nada de fuera.
  - Tema según el dispositivo, siempre claro o siempre oscuro.

  El servidor exige contraste AA en cada tema que se usa: 4,5:1 para el texto sobre el fondo (con un degradado, sobre los dos colores; con una imagen, sobre el píxel más claro y el más oscuro bajo el oscurecido) y para el texto del botón; 3:1 para el botón contra el fondo, o 4,5:1 si el botón es de contorno. Si no llega, no se guarda y el campo dice qué par falla. El formulario tiene una vista previa en directo. Las imágenes van al disco `images` (`link-page/AAAA/MM`), y la anterior se borra al cambiarla o quitarla. No hay CSS libre: todo sale de listas cerradas y colores `#RRGGBB`.
- **Panel**: *Contenido → Página de enlaces*, con el permiso «Configurar la página de enlaces» (`link-page:manage`). Los ajustes se guardan en la tabla `settings` del core y sobreviven a las actualizaciones.

## Requisitos

- GuildForge core `^3.39`
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
