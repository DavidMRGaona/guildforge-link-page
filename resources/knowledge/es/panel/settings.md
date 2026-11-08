---
id: link-page.panel.settings
title: Configurar la página de enlaces
summary: Enciende la página de enlaces, elige qué eventos y qué calendario muestra y cómo se ve al compartirla.
category: content
audience: panel
order: 86
keywords: [página de enlaces, enlaces, linktree, bio, qr, /enlaces, ajustes, configurar, presentación, próximos eventos, eventos fijados, eventos escondidos, calendario, tarjeta, compartir, whatsapp, telegram, buscadores, apagar la página]
related: [link-page.panel.appearance, link-page.panel.notice-and-featured-event, link-page.panel.links-and-socials, link-page.panel.qr-codes, link-page.panel.stats]
conditions:
  permissions:
    all: ['link-page:settings.manage']
---
La página de enlaces es una página pensada para el móvil que reúne en un solo sitio los próximos eventos, el calendario, tus enlaces y tus redes. Está en la dirección `/enlaces` de {{site_name}} y es la que se abre al escanear un cartel con código QR. No tiene la cabecera ni el menú de la web: solo lo esencial.

Todo se configura en una sola pantalla. En el menú lateral, abre **Contenido** y elige **Página de enlaces**. Verás las secciones una debajo de otra; al terminar pulsa **Guardar** (los cambios de todas las secciones se guardan a la vez).

[[action:filament.admin.pages.link-page-settings|Abrir los ajustes de la página]]

## Encender o apagar la página

En la sección **Página** está el interruptor **Página activa**. Apagado, la dirección `/enlaces` responde «Página no encontrada». Los códigos QR ya impresos siguen funcionando: en ese caso llevan a la portada de la web en lugar de a un error.

En **Texto de presentación** escribes unas líneas que salen debajo del nombre del sitio (hasta 500 caracteres). Déjalo vacío si no quieres ninguna.

## Qué eventos salen

La sección **Próximos eventos** decide los botones de eventos de la página. Solo salen eventos publicados, del más cercano al más lejano.

- **Máximo de eventos**: cuántos botones como mucho, de 1 a 20, contando los fijados. Por defecto, 5.
- **Horizonte**: solo salen los eventos que empiezan dentro de estos días, contando hoy (de 1 a 365). Por defecto, 60.
- **Etiquetas**: si eliges alguna, solo salen los eventos que tengan al menos una. Vacío, salen todos.
- **Eventos fijados**: salen siempre arriba mientras no hayan terminado, aunque queden fuera del horizonte o de las etiquetas.
- **Eventos escondidos**: no salen nunca, ni siquiera si están fijados. Sirve para quitar de la página un evento que sí está publicado en la web.

> [!TIP]
> Al abrir la pantalla se descartan solos los eventos fijados o escondidos que ya han terminado, para que no estorben al guardar.

## El calendario

En la sección **Calendario**, **Vista inicial** elige cómo se abre: **Lista** (se lee mejor en el móvil) o **Mes**. Quien visita la página puede cambiar de vista. Debajo del calendario hay un botón **Suscribirse** para seguirlo en el móvil, Google Calendar u Outlook sin necesitar cuenta; a quien ha iniciado sesión, además, le sale **Mi calendario**, que le lleva a su perfil para suscribirse a lo suyo.

## Qué se ve al compartir la página

La sección **Compartir** controla la tarjeta que WhatsApp, Telegram y las redes sociales enseñan cuando alguien pega la dirección, y el botón **Compartir** de la propia página. Lo que dejes vacío se toma del sitio.

- **Título de la tarjeta** (hasta 70 caracteres): vacío, el nombre del sitio.
- **Descripción de la tarjeta** (hasta 160): vacía, el texto de presentación y, si tampoco hay, la descripción del sitio.
- **Imagen de la tarjeta**: JPG o PNG de hasta 1 MB. Se recorta desde el centro a 1200 × 630 píxeles, así que deja los textos y logos en la parte central. Sin imagen se usa el logo del sitio.
- **No indexar en buscadores**: pide a Google y otros buscadores que no muestren la página en sus resultados. Quien tenga el enlace o el QR sigue entrando.

La **Vista previa de la tarjeta** se actualiza mientras escribes. Una imagen recién subida se prepara al guardar.

## Otras secciones

Cada una tiene su guía:

- **Aviso temporal** y **Evento destacado**: lo puntual de arriba.
- **Redes sociales** y los enlaces propios.
- **Apariencia**: colores, fondo, botones y letra.
- **Estadísticas**: cuánto tiempo se conservan los recuentos.

## Si algo no sale bien

- **Un evento no aparece**: comprueba que está publicado, que empieza dentro del **Horizonte**, que tiene alguna de las **Etiquetas** (si has elegido) y que no está en **Eventos escondidos**.
- **No me deja guardar**: baja hasta el campo marcado en rojo; el mensaje dice qué falla (un contraste insuficiente en **Apariencia**, un enlace no válido, un número fuera de rango…).
- **WhatsApp enseña la tarjeta antigua**: las aplicaciones guardan la tarjeta un tiempo. Prueba a pegar la dirección en una conversación nueva pasados unos minutos.
