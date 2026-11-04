# VitalPepShop

Código de **vitalpep.shop**, el host de pago y seguimiento de pedidos de
[vitalpeptides.app](https://vitalpeptides.app). El sitio corre en WordPress.com
(WooCommerce, tema Assembler); este repo contiene el plugin
`vitalpeptides-shop`, que:

- viste el sitio con la marca del storefront (cabecera, pie, Geist, paleta
  navy/azul, logo e icono) sin cambiar de tema;
- añade **Rastrear pedido** (`/rastrear-pedido/`): número de pedido + correo →
  estado del pago, guía y liga de rastreo, CLABE si falta el SPEI; la misma
  caja aparece en la página de gracias y en Mi cuenta → Pedido;
- añade **Reseñas verificadas** (`/resenas/`) con todas las reseñas aprobadas
  de WooCommerce y un resumen en portada y bajo el checkout;
- crea la **portada** que explica qué se hace aquí (pagar, rastrear, cuenta).

El checkout de bloques, el puente `?vp-buy` / `?vp-cart` y los mu-plugins
`vp-*` no se tocan.

## Estructura

```
vitalpeptides-shop.php         plugin principal (constantes de contacto, carga)
includes/
  class-vp-shop-brand.php      cabecera, pie, CSS, icono, título
  class-vp-shop-rastreo.php    [vp_rastreo], POST /wp-json/vp/v1/rastreo, cajas
  class-vp-shop-resenas.php    [vp_resenas], [vp_resenas_resumen], refuerzo checkout
  class-vp-shop-inicio.php     [vp_inicio]
  class-vp-shop-pages.php      crea páginas, portada, clases de body, WP-CLI
  class-vp-shop-icons.php      SVG en línea
assets/
  css/shop.css · js/shop.js · js/rastreo.js
  fonts/ (Geist) · img/ (logo, icono, vial, pagos, paqueterías)
docs/
  2026-11-03-vitalpep-shop-design.md   diseño y decisiones
  DESPLIEGUE.md                        cómo subirlo y qué verificar
```

## Despliegue

Ver [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md): GitHub Deployments de
WordPress.com con destino `/wp-content/plugins/vitalpeptides-shop`, o `scp` al
host como el resto de plugins. Comandos útiles en el host:

```
wp plugin activate vitalpeptides-shop
wp vp-shop status
wp vp-shop pages
```

## Constantes

Se pueden sobreescribir en `wp-config.php` antes de que cargue el plugin:
`VP_SHOP_STOREFRONT` (https://vitalpeptides.app), `VP_SHOP_EMAIL`
(envios@vitalpep.shop), `VP_SHOP_WA` (5215520791699), `VP_SHOP_WA_TEXTO`.
