<?php
/**
 * Reseñas verificadas.
 *
 * Fuente: las reseñas de producto de WooCommerce aprobadas (las mismas que
 * publica el mu-plugin vp-reviews en /wp-json/vp/v1/reviews y que muestra
 * vitalpeptides.app). Cada una nació de un pedido real: el alta sólo acepta
 * tokens firmados por pedido y todo pasa por moderación.
 *
 * Aquí se vuelven a leer de la base (no del endpoint) por dos razones:
 * 1) vp-reviews recorta a 6 reseñas por producto para la ficha; la página
 *    «Reseñas» muestra TODAS.
 * 2) No depende de una petición HTTP a uno mismo.
 *
 * Regla que se respeta igual que en el storefront: si no hay reseñas aprobadas,
 * los resúmenes (home, checkout) no pintan nada. Ni promedio de cero ni
 * «sé el primero».
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VP_Shop_Resenas {

	const CACHE = 'vp_shop_resenas_v1';
	const TTL   = 300;

	private static $checkout_done = false;

	public static function init() {
		add_shortcode( 'vp_resenas', array( __CLASS__, 'shortcode_todas' ) );
		add_shortcode( 'vp_resenas_resumen', array( __CLASS__, 'shortcode_resumen' ) );
		add_filter( 'the_content', array( __CLASS__, 'checkout_append' ), 20 );
		foreach ( array( 'wp_insert_comment', 'transition_comment_status', 'edit_comment', 'deleted_comment' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}
	}

	public static function flush() {
		delete_transient( self::CACHE );
	}

	/* ---------------------------------------------------------------------
	 * Datos
	 * ------------------------------------------------------------------ */

	public static function datos() {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$data = self::build();
		set_transient( self::CACHE, $data, self::TTL );
		return $data;
	}

	private static function build() {
		$out = array(
			'count'     => 0,
			'rating'    => null,
			'productos' => array(),
			'ultimas'   => array(),
		);
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $out;
		}

		$comments = get_comments(
			array(
				'type'    => 'review',
				'status'  => 'approve',
				'number'  => 500,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			)
		);

		$sum = 0;
		foreach ( $comments as $c ) {
			$rating = (int) get_comment_meta( $c->comment_ID, 'rating', true );
			if ( $rating < 1 || $rating > 5 ) {
				continue;
			}
			$product = wc_get_product( $c->comment_post_ID );
			if ( ! $product || 'publish' !== get_post_status( $c->comment_post_ID ) ) {
				continue;
			}
			$slug = $product->get_slug();
			if ( ! isset( $out['productos'][ $slug ] ) ) {
				$img = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
				$out['productos'][ $slug ] = array(
					'nombre'  => $product->get_name(),
					'slug'    => $slug,
					'imagen'  => $img ? $img : '',
					'url'     => vp_shop_storefront( 'productos/' . $slug ),
					'count'   => 0,
					'sum'     => 0,
					'rating'  => null,
					'resenas' => array(),
				);
			}
			$r = array(
				'autor'      => self::nombre( $c ),
				'rating'     => $rating,
				'fecha'      => gmdate( 'Y-m-d', strtotime( $c->comment_date_gmt ) ),
				'texto'      => self::texto( $c->comment_content ),
				'verificada' => (bool) get_comment_meta( $c->comment_ID, 'verified', true ),
				'producto'   => $product->get_name(),
				'slug'       => $slug,
			);
			$out['productos'][ $slug ]['resenas'][] = $r;
			$out['productos'][ $slug ]['count']++;
			$out['productos'][ $slug ]['sum'] += $rating;
			$out['count']++;
			$sum += $rating;
			if ( count( $out['ultimas'] ) < 6 ) {
				$out['ultimas'][] = $r;
			}
		}

		if ( $out['count'] > 0 ) {
			$out['rating'] = round( $sum / $out['count'], 1 );
		}
		foreach ( $out['productos'] as &$p ) {
			$p['rating'] = $p['count'] > 0 ? round( $p['sum'] / $p['count'], 1 ) : null;
		}
		unset( $p );
		uasort(
			$out['productos'],
			function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);
		return $out;
	}

	/** Nombre de pila + inicial, igual que vp-reviews. */
	private static function nombre( $c ) {
		if ( function_exists( 'vp_reviews_display_name' ) ) {
			return (string) vp_reviews_display_name( $c );
		}
		$name  = trim( preg_replace( '/\s+/u', ' ', (string) $c->comment_author ) );
		$parts = explode( ' ', $name );
		if ( count( $parts ) >= 2 && mb_strlen( $parts[1] ) > 0 ) {
			return $parts[0] . ' ' . mb_strtoupper( mb_substr( $parts[1], 0, 1 ) ) . '.';
		}
		return $name;
	}

	private static function texto( $raw ) {
		$t = trim( wp_strip_all_tags( (string) $raw ) );
		if ( mb_strlen( $t ) > 600 ) {
			$t = mb_substr( $t, 0, 599 ) . '…';
		}
		return $t;
	}

	public static function stars( $rating, $size = 16 ) {
		$html = '<span class="vp-stars" role="img" aria-label="' . esc_attr( number_format_i18n( (float) $rating, 1 ) . ' de 5 estrellas' ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$html .= VP_Shop_Icons::star( $i <= round( (float) $rating ), $size );
		}
		return $html . '</span>';
	}

	private static function fecha_larga( $ymd ) {
		$ts = strtotime( $ymd . ' 12:00:00' );
		return $ts ? wp_date( 'j \d\e F \d\e Y', $ts ) : $ymd;
	}

	/* ---------------------------------------------------------------------
	 * Página completa
	 * ------------------------------------------------------------------ */

	public static function shortcode_todas() {
		$d = self::datos();
		ob_start();
		?>
		<section class="vp-section vp-resenas alignfull">
			<div class="vp-container">
				<header class="vp-resenas__head">
					<div>
						<p class="vp-eyebrow">Reseñas</p>
						<h1 class="vp-h1">Lo que dicen quienes ya compraron</h1>
						<?php if ( $d['count'] > 0 ) : ?>
							<div class="vp-rating-big">
								<span class="vp-rating-big__num"><?php echo esc_html( number_format_i18n( $d['rating'], 1 ) ); ?></span>
								<div>
									<?php echo self::stars( $d['rating'], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<p><?php echo (int) $d['count']; ?> <?php echo 1 === (int) $d['count'] ? 'reseña' : 'reseñas'; ?> de compra verificada</p>
								</div>
							</div>
						<?php endif; ?>
						<p class="vp-lead">Cada reseña se escribe desde un enlace único que mandamos después de la entrega. Sin pedido no hay enlace, y todas pasan por moderación antes de publicarse. Las mismas reseñas aparecen en las fichas de <a href="<?php echo esc_url( vp_shop_storefront( 'productos' ) ); ?>">vitalpeptides.app</a>.</p>
					</div>
					<img class="vp-resenas__img" src="<?php echo esc_url( vp_shop_asset( 'img/lineup-1600.jpg' ) ); ?>" alt="Viales de VitalPeptides con su kit de reconstitución" width="800" height="800" loading="lazy">
				</header>

				<?php if ( 0 === (int) $d['count'] ) : ?>
					<p class="vp-muted">Todavía no hay reseñas publicadas.</p>
				<?php else : ?>
					<div class="vp-resenas__grupos">
						<?php foreach ( $d['productos'] as $p ) : ?>
							<section class="vp-grupo" id="resenas-<?php echo esc_attr( $p['slug'] ); ?>">
								<header class="vp-grupo__head">
									<?php if ( '' !== $p['imagen'] ) : ?>
										<img src="<?php echo esc_url( $p['imagen'] ); ?>" alt="" width="64" height="64" loading="lazy">
									<?php endif; ?>
									<div>
										<h2 class="vp-h3"><?php echo esc_html( $p['nombre'] ); ?></h2>
										<p class="vp-grupo__meta"><?php echo self::stars( $p['rating'], 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span><?php echo esc_html( number_format_i18n( $p['rating'], 1 ) ); ?> · <?php echo (int) $p['count']; ?> <?php echo 1 === (int) $p['count'] ? 'reseña' : 'reseñas'; ?></span></p>
									</div>
									<a class="vp-link" href="<?php echo esc_url( $p['url'] ); ?>">Ver producto <?php echo VP_Shop_Icons::svg( 'arrow-right' ); ?></a>
								</header>
								<ul class="vp-review-list">
									<?php foreach ( $p['resenas'] as $r ) : ?>
										<li class="vp-review">
											<div class="vp-review__top">
												<?php echo self::stars( $r['rating'], 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
												<time datetime="<?php echo esc_attr( $r['fecha'] ); ?>"><?php echo esc_html( self::fecha_larga( $r['fecha'] ) ); ?></time>
											</div>
											<p class="vp-review__text"><?php echo esc_html( $r['texto'] ); ?></p>
											<p class="vp-review__by"><strong><?php echo esc_html( $r['autor'] ); ?></strong><?php if ( $r['verificada'] ) : ?> <span class="vp-verified"><?php echo VP_Shop_Icons::svg( 'badge-check' ); ?> Compra verificada</span><?php endif; ?></p>
										</li>
									<?php endforeach; ?>
								</ul>
							</section>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * Resumen compacto (home, checkout)
	 * ------------------------------------------------------------------ */

	public static function shortcode_resumen( $atts = array() ) {
		$atts = shortcode_atts( array( 'max' => 3, 'titulo' => 'Compra verificada, opinión real' ), (array) $atts, 'vp_resenas_resumen' );
		return self::resumen_html( (int) $atts['max'], (string) $atts['titulo'] );
	}

	public static function resumen_html( $max = 3, $titulo = 'Compra verificada, opinión real' ) {
		$d = self::datos();
		if ( (int) $d['count'] < 1 ) {
			return '';
		}
		$quotes = array_slice( $d['ultimas'], 0, max( 1, (int) $max ) );
		ob_start();
		?>
		<div class="vp-resumen">
			<div class="vp-resumen__agg">
				<p class="vp-eyebrow">Reseñas</p>
				<p class="vp-resumen__num"><?php echo esc_html( number_format_i18n( $d['rating'], 1 ) ); ?><span>/5</span></p>
				<?php echo self::stars( $d['rating'], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<p class="vp-resumen__count"><?php echo (int) $d['count']; ?> <?php echo 1 === (int) $d['count'] ? 'reseña' : 'reseñas'; ?> de compra verificada</p>
				<p class="vp-resumen__title"><?php echo esc_html( $titulo ); ?></p>
				<a class="vp-link" href="<?php echo esc_url( vp_shop_page_url( 'resenas' ) ); ?>">Ver todas <?php echo VP_Shop_Icons::svg( 'arrow-right' ); ?></a>
			</div>
			<ul class="vp-resumen__quotes">
				<?php foreach ( $quotes as $r ) : ?>
					<li class="vp-quote">
						<?php echo self::stars( $r['rating'], 13 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<p>“<?php echo esc_html( mb_strlen( $r['texto'] ) > 170 ? mb_substr( $r['texto'], 0, 168 ) . '…' : $r['texto'] ); ?>”</p>
						<footer><strong><?php echo esc_html( $r['autor'] ); ?></strong> · <?php echo esc_html( $r['producto'] ); ?></footer>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * Refuerzo de confianza bajo el checkout
	 * ------------------------------------------------------------------ */

	public static function checkout_append( $content ) {
		if ( self::$checkout_done || is_admin() || ! function_exists( 'is_checkout' ) ) {
			return $content;
		}
		if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) {
			return $content;
		}
		if ( (int) get_the_ID() !== (int) wc_get_page_id( 'checkout' ) ) {
			return $content;
		}
		self::$checkout_done = true;
		return $content . self::checkout_trust_html();
	}

	public static function trust_chips_html() {
		$chips = array(
			array( 'file-check', '<strong>CoA por lote</strong> · HPLC ≥99%' ),
			array( 'truck', '<strong>Envío 24–72 h</strong> a todo México, rastreable' ),
			array( 'lock', '<strong>Pago seguro</strong> · Clip (Visa, Mastercard, Amex) o SPEI' ),
			array( 'package-check', '<strong>Empaque discreto</strong> y agua + jeringas incluidas' ),
		);
		$html = '<ul class="vp-chips">';
		foreach ( $chips as $c ) {
			$html .= '<li>' . VP_Shop_Icons::svg( $c[0] ) . '<span>' . wp_kses( $c[1], array( 'strong' => array() ) ) . '</span></li>';
		}
		return $html . '</ul>';
	}

	public static function legal_html() {
		return '<p class="vp-legal">' . VP_Shop_Icons::svg( 'shield-alert' ) . '<span>Productos para investigación científica. No son medicamentos autorizados por COFEPRIS ni suplementos alimenticios. No sustituyen consulta médica ni receta. El comprador asume la responsabilidad de uso.</span></p>';
	}

	public static function checkout_trust_html() {
		$html  = '<section class="vp-checkout-trust" aria-label="Garantías VitalPeptides">';
		$html .= self::trust_chips_html();
		$html .= self::resumen_html( 3, 'Lo que dicen quienes ya compraron' );
		$html .= '<p class="vp-checkout-trust__help">' . VP_Shop_Icons::svg( 'whatsapp' ) . ' ¿Dudas antes de pagar? <a href="' . esc_url( vp_shop_wa_url( 'Hola VitalPeptides, tengo una duda antes de pagar mi pedido.' ) ) . '" target="_blank" rel="noopener">Escríbenos por WhatsApp</a>.</p>';
		$html .= self::legal_html();
		$html .= '</section>';
		return $html;
	}
}
