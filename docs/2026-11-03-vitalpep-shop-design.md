# vitalpep.shop — diseño de marca, rastreo de pedidos y reseñas

Fecha: 3 de noviembre de 2026. Estado: implementado en este repo (plugin
`vitalpeptides-shop`), pendiente de desplegar y probar en el sitio.

## Intención

vitalpep.shop es el host de pago de vitalpeptides.app: todo botón «Agregar al
carrito» del storefront termina en `vitalpep.shop/?vp-cart=…` y de ahí en
`/checkout/`. Hoy el sitio se ve como una instalación genérica de WordPress
(tema Assembler sin personalizar, pie con «Designed with WordPress» y un correo
de Pontem Advisory). Quien llega a pagar cambia de dominio **y** de identidad
visual justo antes de sacar la tarjeta.

Objetivo: que vitalpep.shop se vea como la misma marca (cabecera, pie,
tipografía, color, logo) y además sirva para lo único que un cliente viene a
hacer aquí aparte de pagar: **saber dónde va su pedido**. De paso, reunir las
reseñas verificadas en una página propia y usarlas como refuerzo de confianza
en el checkout.

El objetivo principal se mantiene: **el checkout sigue siendo el centro y no se
toca su flujo** (puente vp-buy/vp-cart, Store API, Clip, SPEI, CFDI).

## Metas

1. Marca coherente con vitalpeptides.app en todas las páginas de vitalpep.shop
   (checkout incluido) sin cambiar de tema ni editar plantillas a mano.
2. Página pública «Rastrear pedido»: número de pedido + correo → estado del
   pago, línea de tiempo, guía y liga de rastreo, datos SPEI si falta pagar.
3. La misma caja de estado en la página de gracias y en Mi cuenta → Pedido.
4. Página «Reseñas verificadas» con todas las reseñas aprobadas de WooCommerce
   agrupadas por producto, y un resumen (promedio + últimas) en portada y bajo
   el checkout.
5. Portada que explique qué se hace aquí y lleve a rastreo, Mi cuenta y
   catálogo.

## No-metas

- No es una tienda para navegar: el catálogo, CoA, blog y calculadora siguen
  en vitalpeptides.app. El pie y el menú enlazan allá.
- No se cambia el tema, ni plantillas en la base de datos, ni ningún mu-plugin
  existente.
- No se tocan correos (vp-correo-marca ya los viste) ni el flujo de pago.
- No se inventan reseñas ni se muestra un promedio si no hay reseñas aprobadas.

## Anti-metas (lo que haría de esto un fracaso)

- Romper o ralentizar el checkout de bloques.
- Exponer datos de un pedido a quien no tiene número + correo (dirección
  completa, teléfono, RFC nunca se muestran).
- Que al desactivar el plugin el sitio quede peor que antes.

## Restricciones encontradas

- WordPress.com Business, tema de bloques Assembler, PHP 8.4, WooCommerce con
  checkout de bloques. Cabecera y pie son partes de plantilla guardadas en la
  base de datos (editadas en el editor del sitio).
- No hay repo con el código del sitio: `VitalPepShop` estaba vacío. El sitio se
  ha mantenido con mu-plugins subidos por `scp` (alias `vpshop`) y `wp` CLI.
- La guía de envío no está en meta del pedido: Planeta Envíos la escribe como
  **nota privada** («Guía de envío creada - DHL Express Domestic - Tracking: N»).
  `vp-shipping-notify` ya la parsea; se reutiliza su parser.
- La liga de pago pendiente vive en `_vp_clip_payment_url`; la CLABE de SPEI en
  `woocommerce_bacs_accounts`.
- Las reseñas viven como comentarios tipo `review` aprobados, con meta `rating`.
  `vp-reviews` las publica en `/wp-json/vp/v1/reviews` (22 el 3-nov-2026) pero
  recorta a 6 por producto.

## Enfoques considerados

1. **Tema hijo de Assembler** (theme.json + parts/header.html + footer.html +
   plantillas). Control total, pero obliga a cambiar el tema activo en una
   tienda en producción, pierde las personalizaciones del editor del sitio y
   no se puede probar sin un staging. Descartado por riesgo/beneficio.
2. **Plugin que sustituye la salida de las partes de plantilla** (filtro
   `render_block` sobre `core/template-part`) + CSS de marca + páginas con
   shortcodes. Cero cambios al tema y a la base de datos salvo tres páginas y
   la portada; reversible con «Desactivar». **Elegido.**
3. **Reconstruir vitalpep.shop como app aparte** y dejar WooCommerce sólo como
   API. Fuera de escala: el checkout, Clip, SPEI, CFDI y 37 mu-plugins ya
   funcionan ahí.

## Decisiones

- **Un plugin normal** (`wp-content/plugins/vitalpeptides-shop`), no mu-plugin:
  se activa/desactiva desde el panel, y encaja con GitHub Deployments de
  WordPress.com (destino `/wp-content/plugins/vitalpeptides-shop`). También se
  puede subir por `scp` como siempre.
- **Cabecera del checkout mínima** (logo, «Pago seguro», ayuda por WhatsApp):
  la plantilla de pago de WooCommerce usa la parte `checkout-header`; menos
  salidas durante el pago. El resto de páginas lleva la cabecera completa con
  barra de utilidad (WhatsApp, correo, horario, envío gratis +$2,000).
- **Pie calcado del storefront** (Tienda / Tu pedido / Documentación /
  Contacto, logos de pago y paqueterías, aviso RUO, legales). Sin newsletter:
  ya se captura en vitalpeptides.app.
- **Rastreo sin cuenta**: número de pedido + correo de facturación. Límite de
  20 consultas por IP cada 10 min. El servidor devuelve el HTML ya pintado; el
  JS sólo lo inserta (sin JS el formulario hace GET y se pinta igual).
- **Estados en español y con acción**: pendiente → botón «Pagar ahora» (liga
  de Clip); SPEI en espera → CLABE + referencia; procesando → «preparando»;
  con guía → logo de paquetería + «Rastrear en DHL»; cancelado/reembolsado →
  explicación y vuelta al catálogo.
- **Reseñas desde la base**, no desde el endpoint: la página muestra todas;
  los resúmenes se ocultan si no hay ninguna aprobada (misma regla que el
  storefront).
- **Contacto**: `envios@vitalpep.shop` y WhatsApp +52 55 2079 1699 (la verdad
  única de `src/lib/contact.ts`). Desaparece `soporte@pontemadvisory.org` del
  pie.
- **Tipografía** Geist/Geist Mono autoalojadas (copiadas del storefront);
  logo = marca de cadena peptídica en SVG + wordmark, igual que `Logo.tsx`.

## Lo que queda para quien despliegue

- Probar primero en un sitio de staging de WordPress.com (Hosting → Staging).
- Verificar en producción el flujo `?vp-cart=…` → checkout → gracias, y que la
  cabecera mínima aparece en /checkout/ (si la plantilla no usa
  `checkout-header`, aparecerá la cabecera completa: no rompe nada).
- Si el tema usara otro slug para cabecera/pie, el filtro también detecta por
  área y por la etiqueta `<header>/<footer>` del HTML renderizado.
- Enlazar «Rastrear pedido» desde el correo de guía (`vp-shipping-notify`):
  `https://vitalpep.shop/rastrear-pedido/?pedido={id}&correo={email}`.
- Regenerar `src/lib/reviews-snapshot.server.json` en el storefront (estaba
  en 7 reseñas; hay 22).
