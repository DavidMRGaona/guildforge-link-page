---
id: link-page.panel.notice-and-featured-event
title: Poner un aviso y destacar un evento en la página de enlaces
summary: Avisa de lo puntual con una línea arriba del todo y da protagonismo a un evento con su cuenta atrás.
category: content
audience: panel
order: 88
keywords: [aviso, aviso temporal, anuncio, cerrado, cambio de horario, evento destacado, destacar un evento, cuenta atrás, cuenta regresiva, banner, importante, fechas de inicio y fin, página de enlaces, hoy, ahora mismo]
related: [link-page.panel.settings, link-page.panel.appearance, link-page.panel.stats]
conditions:
  permissions:
    all: ['link-page:settings.manage']
---
Dos bloques de la página de enlaces sirven para lo que cambia a menudo: el **Aviso temporal**, una línea arriba del todo, y el **Evento destacado**, un evento en grande bajo el logo. Se configuran en **Sistema** → **Configuración** → **Página de enlaces**, en sus propias secciones.

[[action:filament.admin.configuracion.pages.link-page-settings|Abrir los ajustes de la página]]

## Aviso temporal

Pensado para cosas como «Este sábado el local está cerrado». Solo hay un aviso a la vez.

1. Activa **Mostrar el aviso**. Apagado, el aviso se guarda pero no se muestra.
2. Escribe el **Texto del aviso**: corto y directo, hasta 160 caracteres.
3. Elige el **Tono**: **Información**, **Aviso** o **Importante**. Cambia el icono, el borde y la palabra que precede al texto, así que se distingue sin depender del color.
4. Si quieres que lleve a algún sitio, rellena **Enlace** con una dirección que empiece por `https://` o con una ruta del sitio, como `/eventos`, y, si quieres, el **Texto del enlace** (vacío, sale «Más información»). Cualquier otra cosa se rechaza al guardar.
5. Rellena **Mostrar desde** y **Mostrar hasta** si el aviso solo vale unos días. Las horas son las del sitio. Vacío el inicio, se muestra desde ya; vacío el final, hasta que lo apagues. La hora final ya no se muestra.
6. Pulsa **Guardar**.

Fuera de sus fechas el aviso desaparece solo, sin que tengas que volver a entrar.

## Evento destacado

Un evento con su imagen, la fecha, el lugar y una cuenta atrás. Solo se pueden destacar eventos publicados. En **Qué evento destacar** eliges:

- **No destacar ninguno** (por defecto).
- **Automático: el próximo evento**: el que empieza antes entre los que no han terminado. Un evento en curso sigue destacado hasta que acaba; después pasa al siguiente. Con **Solo con la etiqueta** limitas la búsqueda a los eventos con esa etiqueta. Los eventos que has escondido de la lista tampoco se destacan.
- **Elegido a mano**: el **Evento** que escojas, que desaparece de la página cuando termina.

El evento destacado no se repite en **Próximos eventos**.

### La cuenta atrás

- Antes del día del evento cuenta el tiempo que falta en días, horas y minutos; en la última hora, también los segundos.
- El día del evento antes de empezar pone **Hoy**, y mientras dura pone **Ahora mismo** (con «Hasta el …» si dura varios días).
- Cuando termina, la página pide sola el siguiente evento, o quita el bloque si no hay más.
- La cuenta usa la hora del servidor, así que sale bien aunque el móvil tenga el reloj o la zona mal puestos. Para quien prefiere menos movimiento, los segundos no se animan.

## Si algo no sale bien

- **El aviso no aparece**: comprueba que **Mostrar el aviso** está encendido y que la fecha actual está entre **Mostrar desde** y **Mostrar hasta**.
- **No deja guardar el aviso**: el final tiene que ser posterior al inicio, y el enlace debe empezar por `https://` o por `/`.
- **No sale ningún evento destacado en automático**: no hay ningún evento publicado que no haya terminado (con esa etiqueta, si la pusiste) o todos están escondidos.
- **El evento elegido a mano desapareció**: terminó o se despublicó. Elige otro.
