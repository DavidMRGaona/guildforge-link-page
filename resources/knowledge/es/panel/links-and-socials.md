---
id: link-page.panel.links-and-socials
title: Añadir enlaces propios y redes a la página de enlaces
summary: Crea botones a tu Discord o a un formulario, ordénalos, prográmalos y elige qué redes se muestran.
category: content
audience: panel
order: 89
keywords: [enlaces propios, botones, discord, formulario, enlace destacado, ordenar, arrastrar, programar un enlace, redes sociales, instagram, telegram, whatsapp, youtube, twitch, correo, teléfono, iconos, página de enlaces, mailto, tel]
related: [link-page.panel.settings, link-page.panel.appearance, link-page.panel.stats]
conditions:
  permissions:
    any: ['link-page:links.manage', 'link-page:settings.manage']
---
Además de los eventos y el calendario, la página de enlaces lleva tus propios botones (el Discord, el formulario de socios…) y una fila de iconos con las redes de {{site_name}}.

## Enlaces propios

Se gestionan en **Contenido** → **Enlaces propios**.

1. Pulsa **Crear enlace**.
2. Escribe el **Título** y la **Dirección**. Solo se admiten direcciones `https://`, `mailto:` (un correo) y `tel:` (un teléfono). Cualquier otra se rechaza.
3. Si quieres, añade una **Descripción corta** (una línea bajo el título) y un **Icono**, que se dibuja a la izquierda.
4. Elige la **Posición**: **Antes de los eventos** o **Después de los eventos**.
5. Marca **Destacado** si debe verse más grande, con una insignia y un brillo suave (quieto para quien prefiere menos movimiento).
6. Deja **Activo** encendido. Apagado, el enlace no se muestra aunque esté dentro de sus fechas.
7. Pulsa **Crear**.

### Ordenar

En la lista, **arrastra las filas** para cambiar el orden: se guarda al soltar. Dentro de cada posición manda el orden de la lista.

### Programar

En la sección **Programación**, **Se muestra desde** y **Se muestra hasta** limitan el enlace a unos días, por ejemplo la inscripción a una jornada. Las horas son las del sitio y el final ya no se muestra. La columna **Estado** dice si cada enlace está **Visible**, **Programado**, **Caducado** u **Oculto**.

### Borrar

Borrar un enlace lo quita de la página y no se puede recuperar. Se pide confirmar y marcar la casilla «Entiendo que se borra para siempre».

## Redes sociales

La fila de iconos (Instagram, Discord, Telegram, WhatsApp, YouTube, Twitch, X, Facebook, TikTok, Bluesky, correo y teléfono) **no guarda direcciones propias**: usa las del sitio, para que todas las partes de la web enseñen las mismas. Para cambiar una dirección, ve a **Páginas** → **Nosotros** y abre las pestañas **Redes sociales** o **Contacto**.

Para elegir cuáles salen y dónde, abre **Contenido** → **Página de enlaces** → **Redes sociales**:

- **Dónde va la fila**: **Arriba, bajo el nombre** o **Abajo, al final de la página**.
- **Redes que se muestran**: marca las que quieras. Debajo de cada una ves la dirección que tiene el sitio. Una red sin dirección en el sitio no se puede marcar y, si el sitio la borra más adelante, su icono desaparece de la página aunque siga marcada.

Mientras no cambies nada, se muestran todas las redes que tenga el sitio.

[[action:filament.admin.pages.link-page-settings|Abrir los ajustes de la página|if=link-page:settings.manage]]

## Si algo no sale bien

- **«No es una dirección válida»**: la dirección debe empezar por `https://`, `mailto:` o `tel:`. Las `http://` sin la «s» no se admiten.
- **Una red no se puede marcar**: el sitio aún no tiene su dirección. Añádela en **Páginas** → **Nosotros**.
- **No veo mi enlace en la página**: mira su **Estado** en la lista. Si está **Oculto**, actívalo; si está **Programado** o **Caducado**, ajusta las fechas.
