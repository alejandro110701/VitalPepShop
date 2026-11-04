<?php
/**
 * Plugin Name: VitalPeptides Shop — marca, rastreo de pedidos y reseñas
 * Plugin URI: https://github.com/alejandro110701/VitalPepShop
 * Description: Viste vitalpep.shop con el diseño de vitalpeptides.app (cabecera, pie, tipografía Geist, paleta navy/azul), añade la página «Rastrear pedido» (estado del pago, guía y liga de rastreo), la página «Reseñas» con todas las reseñas verificadas, y refuerzos de confianza en el checkout. No toca el flujo de pago: el checkout de bloques de WooCommerce y el puente vp-buy/vp-cart siguen intactos.
 * Version: 1.0.0
 * Author: VitalPeptides
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Text Domain: vitalpeptides-shop
 *
 * QUÉ HACE Y QUÉ NO
 * - Sustituye SOLO la salida de las partes de plantilla «header», «checkout-header»
 *   y «footer» del tema Assembler (filtro render_block). El tema, las páginas de
 *   WooCommerce y los 37 mu-plugins vp-* siguen igual.
 * - Crea tres páginas al activarse (Inicio → portada, Rastrear pedido, Reseñas).
 *   Al desactivarse devuelve la portada a como estaba; no borra páginas.
 * - Endpoint público POST /wp-json/vp/v1/rastreo (número de pedido + correo).
 *
 * APAGADO RÁPIDO: desactivar el plugin. Todo vuelve al estado anterior.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VP_SHOP_VERSION', '1.0.0' );
define( 'VP_SHOP_FILE', __FILE__ );
define( 'VP_SHOP_DIR', plugin_dir_path( __FILE__ ) );
define( 'VP_SHOP_URL', plugin_dir_url( __FILE__ ) );

/* Verdad única de contacto. Coincide con src/lib/contact.ts del storefront. */
if ( ! defined( 'VP_SHOP_STOREFRONT' ) ) {
	define( 'VP_SHOP_STOREFRONT', 'https://vitalpeptides.app' );
}
if ( ! defined( 'VP_SHOP_EMAIL' ) ) {
	define( 'VP_SHOP_EMAIL', 'envios@vitalpep.shop' );
}
if ( ! defined( 'VP_SHOP_WA' ) ) {
	define( 'VP_SHOP_WA', '5215520791699' );
}
if ( ! defined( 'VP_SHOP_WA_TEXTO' ) ) {
	define( 'VP_SHOP_WA_TEXTO', '+52 55 2079 1699' );
}

require_once VP_SHOP_DIR . 'includes/class-vp-shop-icons.php';
require_once VP_SHOP_DIR . 'includes/class-vp-shop-brand.php';
require_once VP_SHOP_DIR . 'includes/class-vp-shop-rastreo.php';
require_once VP_SHOP_DIR . 'includes/class-vp-shop-resenas.php';
require_once VP_SHOP_DIR . 'includes/class-vp-shop-inicio.php';
require_once VP_SHOP_DIR . 'includes/class-vp-shop-pages.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		VP_Shop_Brand::init();
		VP_Shop_Rastreo::init();
		VP_Shop_Resenas::init();
		VP_Shop_Inicio::init();
		VP_Shop_Pages::init();
	}
);

register_activation_hook( __FILE__, array( 'VP_Shop_Pages', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VP_Shop_Pages', 'deactivate' ) );

/* -------------------------------------------------------------------------
 * Ayudantes compartidos
 * ---------------------------------------------------------------------- */

/** Liga de WhatsApp con texto prellenado. */
function vp_shop_wa_url( $texto = '' ) {
	$url = 'https://wa.me/' . VP_SHOP_WA;
	if ( '' !== $texto ) {
		$url .= '?text=' . rawurlencode( $texto );
	}
	return $url;
}

/** URL dentro del storefront (vitalpeptides.app). */
function vp_shop_storefront( $path = '' ) {
	return rtrim( VP_SHOP_STOREFRONT, '/' ) . '/' . ltrim( (string) $path, '/' );
}

/** URL de una de las páginas que crea este plugin (inicio, rastrear-pedido, resenas). */
function vp_shop_page_url( $slug ) {
	return VP_Shop_Pages::url( $slug );
}

/** URL pública de un archivo de assets/. */
function vp_shop_asset( $rel ) {
	return VP_SHOP_URL . 'assets/' . ltrim( (string) $rel, '/' );
}
