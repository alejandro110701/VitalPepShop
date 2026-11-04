<?php
/**
 * Páginas que crea el plugin y clases de cuerpo.
 *
 * - inicio           → portada del sitio ([vp_inicio])
 * - rastrear-pedido  → [vp_rastreo]
 * - resenas          → [vp_resenas]
 *
 * Se crean al activar (o con `wp vp-shop pages`). Si ya existe una página con
 * ese slug se reutiliza y no se toca su contenido. Al desactivar el plugin la
 * portada vuelve a la configuración anterior; las páginas se quedan.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VP_Shop_Pages {

	const OPT_PAGES      = 'vp_shop_pages';
	const OPT_FRONT_PREV = 'vp_shop_front_prev';
	const OPT_VERSION    = 'vp_shop_version';

	public static function definitions() {
		return array(
			'inicio'          => array( 'title' => 'Inicio', 'shortcode' => 'vp_inicio' ),
			'rastrear-pedido' => array( 'title' => 'Rastrear pedido', 'shortcode' => 'vp_rastreo' ),
			'resenas'         => array( 'title' => 'Reseñas verificadas', 'shortcode' => 'vp_resenas' ),
		);
	}

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function activate() {
		self::install();
	}

	public static function deactivate() {
		self::restore_front();
	}

	/** Si se actualizó el plugin por scp/deploy sin reactivar, completa lo que falte. */
	public static function maybe_install() {
		if ( get_option( self::OPT_VERSION ) !== VP_SHOP_VERSION ) {
			self::install();
		}
	}

	public static function install() {
		$ids = (array) get_option( self::OPT_PAGES, array() );
		foreach ( self::definitions() as $slug => $def ) {
			$page = self::find( $slug, $ids );
			if ( $page ) {
				$ids[ $slug ] = (int) $page->ID;
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => $def['title'],
					'post_name'      => $slug,
					'post_content'   => '<!-- wp:shortcode -->[' . $def['shortcode'] . ']<!-- /wp:shortcode -->',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				),
				true
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$ids[ $slug ] = (int) $id;
			}
		}
		update_option( self::OPT_PAGES, $ids, false );

		if ( ! empty( $ids['inicio'] ) && (int) get_option( 'page_on_front' ) !== (int) $ids['inicio'] ) {
			if ( ! get_option( self::OPT_FRONT_PREV ) ) {
				update_option(
					self::OPT_FRONT_PREV,
					array(
						'show_on_front' => (string) get_option( 'show_on_front' ),
						'page_on_front' => (int) get_option( 'page_on_front' ),
					),
					false
				);
			}
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $ids['inicio'] );
		}

		update_option( self::OPT_VERSION, VP_SHOP_VERSION, false );
		flush_rewrite_rules();
	}

	public static function restore_front() {
		$prev = get_option( self::OPT_FRONT_PREV );
		if ( is_array( $prev ) ) {
			update_option( 'show_on_front', ! empty( $prev['show_on_front'] ) ? $prev['show_on_front'] : 'posts' );
			update_option( 'page_on_front', isset( $prev['page_on_front'] ) ? (int) $prev['page_on_front'] : 0 );
			delete_option( self::OPT_FRONT_PREV );
		}
		delete_option( self::OPT_VERSION );
		flush_rewrite_rules();
	}

	private static function find( $slug, $ids ) {
		if ( ! empty( $ids[ $slug ] ) ) {
			$p = get_post( (int) $ids[ $slug ] );
			if ( $p && 'page' === $p->post_type && 'trash' !== $p->post_status ) {
				return $p;
			}
		}
		$p = get_page_by_path( $slug, OBJECT, 'page' );
		return ( $p && 'trash' !== $p->post_status ) ? $p : null;
	}

	public static function id( $slug ) {
		$p = self::find( $slug, (array) get_option( self::OPT_PAGES, array() ) );
		return $p ? (int) $p->ID : 0;
	}

	public static function url( $slug ) {
		$id = self::id( $slug );
		return $id ? get_permalink( $id ) : home_url( '/' . $slug . '/' );
	}

	public static function body_class( $classes ) {
		$classes[] = 'vp-shop';
		if ( is_page() ) {
			$qid = (int) get_queried_object_id();
			foreach ( array_keys( self::definitions() ) as $slug ) {
				if ( $qid && $qid === self::id( $slug ) ) {
					$classes[] = 'vp-page-' . $slug;
				}
			}
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$classes[] = 'vp-checkout';
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			$classes[] = 'vp-account';
		}
		return $classes;
	}
}

/* -------------------------------------------------------------------------
 * WP-CLI:  wp vp-shop pages | status
 * ---------------------------------------------------------------------- */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class VP_Shop_CLI {
		/** Crea las páginas (inicio, rastrear-pedido, resenas) y fija la portada. */
		public function pages() {
			VP_Shop_Pages::install();
			WP_CLI::success( 'Páginas listas: ' . wp_json_encode( get_option( VP_Shop_Pages::OPT_PAGES ) ) );
		}

		/** Muestra páginas, portada y reseñas detectadas. */
		public function status() {
			$ids = (array) get_option( VP_Shop_Pages::OPT_PAGES, array() );
			foreach ( $ids as $slug => $id ) {
				WP_CLI::log( sprintf( '%-16s #%d  %s', $slug, $id, get_permalink( $id ) ) );
			}
			WP_CLI::log( 'show_on_front=' . get_option( 'show_on_front' ) . '  page_on_front=' . get_option( 'page_on_front' ) );
			$r = VP_Shop_Resenas::datos();
			WP_CLI::log( sprintf( 'Reseñas aprobadas: %d (promedio %s) en %d productos', $r['count'], (string) $r['rating'], count( $r['productos'] ) ) );
		}
	}
	WP_CLI::add_command( 'vp-shop', 'VP_Shop_CLI' );
}
