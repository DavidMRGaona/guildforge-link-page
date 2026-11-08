---
id: link-page.panel.appearance
title: Cambiar la apariencia de la página de enlaces
summary: Elige una plantilla o personaliza los colores, el fondo, los botones y la letra, con el contraste siempre comprobado.
category: content
audience: panel
order: 87
keywords: [apariencia, colores, plantilla, tema claro, tema oscuro, fondo, degradado, imagen de fondo, botones, letra, tipografía, logo, contraste, legibilidad, accesibilidad, diseño, estilo, no me deja guardar los colores]
related: [link-page.panel.settings, link-page.panel.notice-and-featured-event]
conditions:
  permissions:
    all: ['link-page:settings.manage']
---
La página de enlaces se adapta a la imagen de {{site_name}} sin necesidad de escribir CSS. En el menú lateral, abre **Sistema** → **Configuración** → **Página de enlaces** y baja hasta la sección **Apariencia**. La sección tiene una **Vista previa** en forma de móvil (al lado de los campos en pantallas anchas, debajo en las estrechas) que se actualiza mientras cambias cosas: un móvil por cada tema que se usa, con el contraste de cada par de colores.

[[action:filament.admin.configuracion.pages.link-page-settings|Abrir los ajustes de la página]]

## Empezar por una plantilla

El campo **Plantilla** rellena todo lo demás de una vez:

- **Colores del sitio** (la opción por defecto): la página sigue la paleta de la web, también si la cambias más adelante.
- **Pergamino**, **Taberna**, **Arcano**, **Bosque** y **Minimal**: combinaciones ya preparadas de colores, fondo, botones y letra.
- **Personalizados**: se elige solo en cuanto cambias a mano cualquier color.

Después de elegir una plantilla puedes retocar lo que quieras.

## Qué se puede cambiar

- **Tema**: **Según el dispositivo** usa el tema que la persona eligió en la web o, si no eligió, el de su móvil; **Siempre claro** y **Siempre oscuro** lo fijan para todos.
- **Logo**: el **Logo del sitio**, una **Imagen propia** (PNG, JPG o WebP de hasta 1 MB, que se recorta para llenar la forma) o la **Inicial del nombre**, en **Círculo** o **Cuadrado redondeado**.
- **Colores**: un juego para el **Tema claro** y otro para el **Tema oscuro**, con el fondo, el texto, el botón y el texto del botón.
- **Fondo**: **Color liso**, **Degradado** (dos colores y un ángulo) o **Imagen** (PNG, JPG o WebP de hasta 4 MB) con un **Oscurecido de la imagen** de 0 a 90 % para que el texto se lea.
- **Estilo de los botones**: **Relleno**, **Contorno** o **Sombra**, con esquinas **Rectas**, **Redondeadas** o de **Píldora**.
- **Letra**: la de los títulos o la del texto del sitio, Inter, Playfair Display o las del dispositivo. No se descarga nada de fuera.

## El contraste se comprueba al guardar

Los colores tienen que leerse bien (nivel AA). Al guardar se revisa cada tema que usas, y si algún par no llega, no se guarda y el campo explica qué falla. Por ejemplo: «Contraste insuficiente en el tema claro: el texto sobre el fondo da 2,3:1 y hace falta 4,5:1».

| Par | Mínimo |
|---|---|
| Texto sobre el fondo (con degradado, sobre los dos colores; con imagen, sobre su parte más clara y más oscura) | 4,5:1 |
| Texto del botón sobre el botón | 4,5:1 |
| Botón relleno o con sombra contra el fondo | 3:1 |
| Botón de contorno (su texto va sobre el fondo) | 4,5:1 |

Con **Colores del sitio** no se comprueba nada porque ya lo mantiene la web. El mismo cuidado se aplica al aviso temporal y a los enlaces destacados: usan pares de colores de los que ya pasaron la prueba.

> [!TIP]
> Si una imagen de fondo tiene zonas muy claras y muy oscuras, sube el **Oscurecido** hasta que el aviso de contraste desaparezca.

## Si algo no sale bien

- **No se guardan los colores**: lee el mensaje del campo; indica el tema y el par que falla. Oscurece el fondo o aclara el texto hasta superar el mínimo.
- **La vista previa dice «Algún valor no es válido todavía»**: se muestra la última apariencia guardada hasta que corrijas el valor.
- **El calendario no se ve igual que en la vista previa**: la vista previa no incluye el calendario, pero este sigue los colores de la página.
