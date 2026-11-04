# Despliegue de `vitalpeptides-shop` en vitalpep.shop

El repo **es** el plugin: la raíz contiene `vitalpeptides-shop.php`, `includes/`
y `assets/`. Se instala en `wp-content/plugins/vitalpeptides-shop/`.

## 0. Antes de tocar producción

WordPress.com → Hosting → **Staging**: crea (o actualiza) el sitio de staging y
haz ahí los pasos 1–3 primero. El checkout de bloques, Clip y SPEI se prueban
igual en staging (Clip en modo prueba o sin completar el pago).

## 1. Opción A — GitHub Deployments (recomendada, deja el repo como fuente)

1. Haz push de este repo a `alejandro110701/VitalPepShop`, rama `main`.
2. WordPress.com → Hosting → sitio **vitalpep.shop** → **Deployments** →
   «Connect repository» → autoriza la app «WordPress.com for Developers» y
   elige el repo `VitalPepShop`.
3. Ajustes:
   - **Deployment branch**: `main`
   - **Destination directory**: `/wp-content/plugins/vitalpeptides-shop`
   - **Deployment mode**: Simple
   - **Automatic deployments**: *Manual* en producción (Automatic en staging)
4. «Trigger manual deployment». El archivo `.deployignore` evita subir docs y
   README.

## 1. Opción B — scp (como el resto de mu-plugins)

```bash
scp -r /Users/alejandrovalencia/Claude/VitalPepShop vpshop:/srv/htdocs/wp-content/plugins/vitalpeptides-shop
```

## 2. Comprobar sintaxis y activar (en el host)

```bash
ssh vpshop 'cd /srv/htdocs/wp-content/plugins/vitalpeptides-shop && for f in vitalpeptides-shop.php includes/*.php; do php -l $f; done'
```

```bash
ssh vpshop 'wp plugin activate vitalpeptides-shop && wp vp-shop status'
```

Al activarse crea (si no existen) las páginas **Inicio** (y la fija como
portada), **Rastrear pedido** (`/rastrear-pedido/`) y **Reseñas verificadas**
(`/resenas/`). Si se subió por scp y ya estaba activo, `wp vp-shop pages`
completa lo que falte.

## 3. Lista de verificación

- [ ] `https://vitalpep.shop/` muestra la portada nueva (hero, tres acciones,
      cómo pagar, reseñas, ayuda) con cabecera y pie de marca.
- [ ] `https://vitalpep.shop/?vp-cart=RETATRUTIDE-10MG:1&replace_cart=1` sigue
      llegando a `/checkout/` con el producto en el resumen.
- [ ] `/checkout/` muestra la cabecera mínima (logo · Pago seguro · ¿Ayuda?),
      el formulario sin cambios, y debajo las garantías + reseñas + aviso RUO.
- [ ] Pago con Clip y pedido SPEI: la página de gracias muestra la caja
      «Seguimiento de tu pedido» con el estado correcto.
- [ ] `/rastrear-pedido/` con un pedido real:
  - pendiente de pago → botón «Pagar ahora»
  - SPEI en espera → CLABE, beneficiario y referencia
  - procesando sin guía → «preparando tu envío»
  - con guía → logo de paquetería y «Rastrear en DHL/FedEx/Estafeta»
  - número o correo incorrectos → mensaje de no encontrado (sin filtrar datos)
- [ ] Mi cuenta → Pedidos → ver pedido: aparece «Estado del envío» y en el menú
      «Rastrear un pedido».
- [ ] `/resenas/` lista las 22 reseñas agrupadas por producto.
- [ ] Móvil (375 px): menú hamburguesa, formulario de rastreo y checkout.
- [ ] El icono del sitio (pestaña) es el de VitalPeptides.

## 4. Vuelta atrás

`wp plugin deactivate vitalpeptides-shop` (o desde Plugins). La portada vuelve
a la configuración anterior; las tres páginas quedan creadas pero no se
enlazan desde ningún sitio. Borrar la carpeta del plugin no deja rastro en el
tema ni en los mu-plugins.

## 5. Después

- En `vp-shipping-notify.php`, el correo de guía puede enlazar a
  `https://vitalpep.shop/rastrear-pedido/?pedido={id}&correo={email}`.
- En el storefront, regenerar `src/lib/reviews-snapshot.server.json`
  (`curl -s https://vitalpep.shop/wp-json/vp/v1/reviews > …`).
