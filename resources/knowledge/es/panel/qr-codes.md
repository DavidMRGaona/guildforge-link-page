---
id: link-page.panel.qr-codes
title: Crear, descargar e imprimir códigos QR
summary: Haz un código QR por cada sitio donde lo pegues, descárgalo o imprímelo en A4 o A5, y compara cuál se escanea más.
category: content
audience: panel
order: 90
keywords: [código qr, qr, carteles, cartel, imprimir, descargar, svg, png, a4, a5, pdf, colores, logo, margen, desactivar, borrar, escaneos, flyer, mesa, tarjeta, imprenta, no se lee el qr]
related: [link-page.panel.stats, link-page.panel.settings]
conditions:
  permissions:
    all: ['link-page:qr_codes.manage']
---
Un código QR lleva a la página de enlaces. Haz **uno por cada sitio** donde lo pongas (el cartel del local, las mesas, las tarjetas, unas jornadas…) y así, en las estadísticas, verás cuál se escanea más.

## La dirección de un QR no cambia nunca

Cada QR tiene un **código** fijo de seis caracteres y su dirección es siempre `/qr/` seguida de ese código. Esa dirección redirige a la página de enlaces (o a la portada de la web si la página está apagada). Por eso puedes cambiar la página, los colores o el diseño sin que un cartel ya impreso deje de funcionar.

- Un QR **desactivado** o **borrado** sigue llevando a la página, pero sus escaneos ya no se cuentan.
- El código de un QR borrado queda reservado para siempre y no se reutiliza.
- Solo un código que nunca existió da «Página no encontrada».

## Crear un QR

En el menú lateral, abre **Contenido** → **Códigos QR** y pulsa **Crear código QR**.

1. **Nombre**: solo se ve en el panel, por ejemplo «Cartel del local».
2. **Frase del cartel**: sale debajo del QR en la versión para imprimir. Vacía, se usa «Escanea para ver los próximos eventos».
3. **Activo**: déjalo encendido para contar los escaneos.
4. En **Diseño**, elige el **Color del código** y el **Color del fondo**, el **Margen** (el espacio en blanco alrededor, en porcentaje; los lectores necesitan algo) y, si quieres, un **Logo en el centro**: **Sin logo**, el **Logo del sitio** o una **Imagen propia** (PNG, JPEG o WebP de hasta 1 MB). Con logo, el código se dibuja con la máxima corrección de errores para que se siga leyendo.
5. Mira la **Vista previa**, que se actualiza al cambiar cualquier cosa (un logo recién subido aparece al guardar).
6. Pulsa **Crear**.

Para que los móviles lo lean, el color del código debe ser **más oscuro** que el del fondo y el contraste debe ser de al menos 4,5:1. Si no se cumple, el campo lo explica y no se guarda.

## Descargarlo

En la lista, abre el menú ⋯ de la fila y pulsa **Descargar**. Elige el **Formato**:

- **SVG (vectorial, para imprenta)**: se puede ampliar sin perder calidad. Es el mejor para carteles grandes y para enviar a una imprenta.
- **PNG** de 256, 512, 1024 o 2048 píxeles: para redes, documentos o pantallas.

## Imprimirlo

En el menú ⋯ de la fila, elige **Imprimir en A4** o **Imprimir en A5**. Se abre una hoja lista para imprimir con el nombre del sitio, el QR (14 cm en A4, 10 cm en A5), la frase del cartel y, debajo, la dirección corta escrita por si alguien no puede escanear.

1. Revisa la hoja en la pantalla.
2. Pulsa **Imprimir**.
3. En el diálogo del navegador, deja los márgenes de página en «ninguno» y la escala al 100 %. Para obtener un PDF, elige «Guardar como PDF» en lugar de una impresora.

## Activar, desactivar y borrar

En el menú ⋯ de la fila:

- **Desactivar** / **Activar**: un QR desactivado sigue llevando a la página, pero deja de contar escaneos. Útil para retirar un cartel antiguo sin perder su historial.
- **Borrar**: el QR desaparece del panel y no se puede recuperar ni volver a descargar. Se pide marcar una casilla para confirmarlo. Los carteles ya impresos siguen llevando a la página.

> [!WARNING]
> Un QR borrado no se puede recuperar. Si solo quieres dejar de contar sus escaneos, desactívalo en su lugar.

## Ver los escaneos

Cada QR cuenta por separado. Las cifras están en **Contenido** → **Estadísticas de enlaces**, en **Códigos QR más escaneados**.

## Si algo no sale bien

- **El móvil no lee el QR**: usa un color del código muy oscuro sobre un fondo claro, deja algo de margen, y si pusiste un logo prueba a quitarlo o a hacerlo más pequeño. Imprime en SVG o en el PNG más grande.
- **«Con estos colores no se puede dibujar un QR que los móviles lean»**: el contraste es demasiado bajo; oscurece el color del código.
- **No se ha podido dibujar el código**: prueba otro tamaño o quita el logo.
- **El QR cuenta pocos escaneos**: los escaneos en ráfaga desde un mismo móvil o una misma conexión no se cuentan; en las estadísticas se explica con detalle.
