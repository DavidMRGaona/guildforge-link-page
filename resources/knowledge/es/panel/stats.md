---
id: link-page.panel.stats
title: Estadísticas de la página de enlaces
summary: Cuántas visitas tiene la página de enlaces, qué cartel con QR se escanea más y qué se pulsa, y qué cuentan y qué no esas cifras.
category: content
audience: panel
order: 92
keywords: [estadísticas, visitas, escaneos, qr, código qr, clics, pulsaciones, carteles, cartel más escaneado, enlaces más pulsados, analítica, métricas, página de enlaces, enlaces, bots, robots, privacidad, cookies]
related: [link-page.panel.qr-codes, link-page.panel.settings, link-page.panel.links-and-socials]
conditions:
  permissions:
    all: ['link-page:stats.view']
---
Las estadísticas dicen si la página de enlaces y sus códigos QR funcionan: cuánta gente la abre, qué cartel se escanea más y qué enlaces se pulsan. Son recuentos anónimos por día: nunca se guarda quién visita, escanea o pulsa.

## Dónde verlas

- En el **Escritorio**, la tarjeta **Página de enlaces** muestra las visitas, los escaneos y los clics de los últimos 30 días. Pulsa una cifra para abrir la página de estadísticas.
- En el menú lateral, abre **Sitio web**, elige **Página de enlaces** y después **Estadísticas de enlaces**. Arriba eliges el **Periodo** (los últimos 7, 30, 90 o 365 días, contando hoy) y debajo salen los totales, la **Evolución por día**, los **Códigos QR más escaneados** y los **Enlaces más pulsados**.

Los días se cuentan con la hora de la web: lo que pasa a las 23:30 cuenta en ese día.

## Qué es cada cifra

- **Visitas**: las veces que alguien abre la página de enlaces en su navegador, llegue por un QR, por un enlace compartido o escribiendo la dirección.
- **Escaneos**: las veces que se abre la dirección de un código QR impreso. Cada código cuenta por separado, así que un código por cartel te dice qué cartel funciona mejor. Un escaneo que acaba en la página cuenta también como visita.
- **Clics**: las pulsaciones en los enlaces propios, los iconos de redes sociales, los botones de los próximos eventos, el evento destacado, el enlace del aviso, las entradas del calendario y el enlace para ir a la web. En el ranking, debajo de cada nombre se ve de cuál se trata (*Enlace*, *Red social*, *Evento*, *Evento destacado*, *Aviso*, *Calendario* o *Web*): un mismo evento puede salir varias veces, como botón, como destacado y como entrada del calendario.

## Qué no se cuenta

Para que las cifras se parezcan a lo que hace la gente de verdad, se dejan fuera:

- **Recargas**: volver a cargar la página, o volver a ella con atrás o adelante, no suma otra visita.
- **Repeticiones**: lo mismo desde el mismo dispositivo en pocos segundos cuenta una vez. Así, un doble toque es un solo clic y una aplicación de escaneo que abre el código dos veces es un solo escaneo.
- **Robots**: buscadores, las vistas previas que preparan WhatsApp, Telegram, Facebook o Discord cuando alguien comparte el enlace, herramientas de comprobación y navegadores automáticos.
- **Precargas**: páginas que el navegador prepara por si acaso y nadie llega a abrir.
- **Quien gestiona el panel**: las visitas y los clics de cualquiera con acceso al panel no cuentan, para que comprobar la página no infle las cifras.
- **Escaneos en ráfaga**: si un mismo dispositivo escanea más de 10 códigos en un minuto, o una misma conexión más de 60, el resto no cuenta. La persona llega igualmente a la página.
- **Códigos QR desactivados o borrados**: siguen llevando a la página, pero sus escaneos ya no suman. Los que tenían se conservan, y el ranking los marca como *desactivado* o *borrado*.

## Lo que las cifras no pueden decir

- **No son personas**: la misma persona puede visitar la página varios días, y cada día cuenta. No hay forma de saberlo, porque no se guarda nada de quién visita.
- **Visitas y clics pueden quedarse algo cortos**: un navegador sin JavaScript o con un bloqueador de rastreo puede no enviar el aviso. Los escaneos no dependen de eso.
- **Algunas formas de abrir un enlace no cuentan**: abrirlo en otra pestaña desde el menú de mantener pulsado, o copiar la dirección.
- **Calendario**: solo cuentan las entradas de los tres meses anteriores al año siguiente, que son las que la gente abre.
- **Mucha gente en la misma wifi**: en un local con mucha afluencia, varias personas detrás de la misma conexión pueden llegar al límite de escaneos y quedarse algo por debajo.
- **Nombres de ahora**: el ranking muestra el título actual de cada enlace. Si un enlace se borra, sus clics salen como *Enlace borrado*; un evento que ya no está, como *Evento que ya no está*.

## Qué se guarda y qué no

Solo un contador por día de cada cosa: la página, cada código QR y cada enlace. Nunca la dirección IP, la cuenta ni el navegador. Las estadísticas tampoco usan cookies ni guardan nada en el navegador de quien visita, así que no necesitan aviso de cookies. Para reconocer las repeticiones y las ráfagas se usa una huella cifrada de la conexión y del navegador que dura como mucho un minuto y nunca llega a las estadísticas.

## Cuánto tiempo se conservan

Se conservan 365 días y cada noche se borran los días más antiguos. Quien configura la página de enlaces puede cambiar el plazo en **Sistema** → **Configuración** → **Página de enlaces** → **Estadísticas** → **Conservar**, entre 30 y 3650 días. Los días borrados no se recuperan.

[[action:filament.admin.pages.link-page-stats|Ver las estadísticas]]
