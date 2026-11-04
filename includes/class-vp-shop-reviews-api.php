<?php
/**
 * /wp-json/vp/v1/reviews sin el tope de 6 reseñas por producto.
 *
 * El mu-plugin vp-reviews publica el agregado que consume vitalpeptides.app
 * (fichas, portada y /resenas), pero recorta cada producto a
 * VP_REVIEWS_PER_PRODUCT = 6. Estaba pensado para la ficha; a /resenas, que
 * quiere mostrarlas todas, se le quedaba corto en cuanto un producto pasara
 * de seis (Tirzepatide ya está en seis).
 *
 * Los mu-plugins cargan antes que los plugins, así que la constante no se
 * puede redefinir desde aquí y el archivo del host sólo se cambia por scp. En
 * vez de eso, este plugin vuelve a registrar LA MISMA ruta (override, en
 * `rest_api_init` con prioridad 20, después del mu-plugin) con el mismo
 * formato de respuesta y hasta 50 reseñas por producto, construidas desde el
 * mismo origen: comentarios tipo `review` aprobados con meta `rating` y
 * `verified` (VP_Shop_Resenas::datos(), que ya alimenta la página /resenas de
 * esta tienda). Si este plugin se desactiva, vuelve a responder el mu-plugin.
 *
 * La cabecera `X-VP-Reviews-Source` dice quién respondió, para comprobarlo
 * con un curl.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VP_Shop_Reviews_API {

	const PER_PRODUCT = 50;

	public static function init() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		add_action( 'rest_api_init', array( __CLASS__, 'register' ), 20 );
	}

	public static function register() {
		register_rest_route(
			'vp/v1',
			'/reviews',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'callback' ),
			),
			true
		);
	}

	/** Misma forma que vp-reviews: generated, site{count,rating}, products{slug:{count,rating,reviews[]}}. */
	public static function payload() {
		$d        = VP_Shop_Resenas::datos();
		$products = array();
		foreach ( $d['productos'] as $slug => $p ) {
			$reviews = array();
			foreach ( array_slice( $p['resenas'], 0, self::PER_PRODUCT ) as $r ) {
				$reviews[] = array(
					'author'   => (string) $r['autor'],
					'rating'   => (int) $r['rating'],
					'date'     => (string) $r['fecha'],
					'text'     => (string) $r['texto'],
					'verified' => (bool) $r['verificada'],
				);
			}
			$products[ $slug ] = array(
				'count'   => (int) $p['count'],
				'rating'  => $p['rating'],
				'reviews' => $reviews,
			);
		}
		return array(
			'generated' => time(),
			'site'      => array(
				'count'  => (int) $d['count'],
				'rating' => $d['rating'],
			),
			// Objeto aunque esté vacío: vitalpeptides.app acepta `[]`, pero
			// `{}` es lo que promete el contrato.
			'products'  => empty( $products ) ? new stdClass() : $products,
		);
	}

	public static function callback() {
		$res = rest_ensure_response( self::payload() );
		// Igual que el mu-plugin: una reseña recién aprobada debe salir pronto.
		$res->header( 'Cache-Control', 'public, max-age=0, s-maxage=60' );
		$res->header( 'Access-Control-Allow-Origin', '*' );
		$res->header( 'X-VP-Reviews-Source', 'vitalpeptides-shop/' . VP_SHOP_VERSION );
		return $res;
	}
}
