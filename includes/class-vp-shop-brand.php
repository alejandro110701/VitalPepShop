<?php
/**
 * Marca: cabecera, pie, tipografía, icono del sitio.
 *
 * CÓMO SE INYECTA
 * Assembler es un tema de bloques: cabecera y pie son «partes de plantilla»
 * (core/template-part) editadas desde el editor del sitio y guardadas en la
 * base de datos. En vez de pelear con eso, el filtro `render_block` sustituye
 * la SALIDA de esas partes por nuestro HTML:
 *   - slug «checkout-header» (la que usa la plantilla de pago de WooCommerce)
 *     → cabecera mínima: logo, candado «Pago seguro» y ayuda por WhatsApp.
 *   - área/slug «header» → cabecera completa con barra de utilidad y menú.
 *   - área/slug «footer» → pie con las mismas columnas que vitalpeptides.app.
 * Desactivar el plugin devuelve las partes originales. El editor del sitio
 * sigue mostrando las partes del tema (no se tocan), sólo que ya no se pintan.
 *
 * TIPOGRAFÍA Y COLOR
 * Geist y Geist Mono se sirven desde assets/fonts (copiadas del storefront).
 * La paleta es la de src/styles.css del storefront: navy #0B2B4F, azul
 * científico #2B6FB8, verde de verificación #4E9A51.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VP_Shop_Brand {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		add_filter( 'render_block', array( __CLASS__, 'template_parts' ), 10, 2 );
		add_filter( 'get_site_icon_url', array( __CLASS__, 'site_icon' ), 10, 2 );
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
	}

	public static function assets() {
		if ( is_admin() ) {
			return;
		}
		$deps = array();
		foreach ( array( 'global-styles', 'wc-blocks-style', 'woocommerce-general' ) as $h ) {
			if ( wp_style_is( $h, 'registered' ) || wp_style_is( $h, 'enqueued' ) ) {
				$deps[] = $h;
			}
		}
		wp_enqueue_style( 'vp-shop', vp_shop_asset( 'css/shop.css' ), $deps, VP_SHOP_VERSION );
		wp_enqueue_script( 'vp-shop', vp_shop_asset( 'js/shop.js' ), array(), VP_SHOP_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	public static function head() {
		echo '<link rel="preload" href="' . esc_url( vp_shop_asset( 'fonts/geist-latin.woff2' ) ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		echo '<meta name="theme-color" content="#0B2B4F">' . "\n";
	}

	public static function site_icon( $url, $size ) {
		return (int) $size <= 48 ? vp_shop_asset( 'img/favicon.ico' ) : vp_shop_asset( 'img/icon-256.png' );
	}

	public static function title_parts( $parts ) {
		if ( isset( $parts['site'] ) ) {
			$parts['site'] = 'VitalPeptides México';
		}
		if ( is_front_page() ) {
			$parts['title'] = 'VitalPeptides México · Pagos y rastreo de pedidos';
			unset( $parts['tagline'] );
		}
		return $parts;
	}

	/* ---------------------------------------------------------------------
	 * Sustitución de partes de plantilla
	 * ------------------------------------------------------------------ */

	public static function template_parts( $html, $block ) {
		if ( is_admin() || empty( $block['blockName'] ) || 'core/template-part' !== $block['blockName'] ) {
			return $html;
		}
		$attrs = ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) ? $block['attrs'] : array();
		$slug  = isset( $attrs['slug'] ) ? (string) $attrs['slug'] : '';
		$area  = isset( $attrs['area'] ) ? (string) $attrs['area'] : '';
		$tag   = isset( $attrs['tagName'] ) ? (string) $attrs['tagName'] : '';

		if ( 'checkout-header' === $slug ) {
			return self::checkout_header_html();
		}
		$is_header = 'header' === $area || 'header' === $tag || 0 === strpos( $slug, 'header' ) || preg_match( '/^\s*<header\b/i', $html );
		$is_footer = 'footer' === $area || 'footer' === $tag || 0 === strpos( $slug, 'footer' ) || preg_match( '/^\s*<footer\b/i', $html );
		if ( $is_header ) {
			return self::header_html();
		}
		if ( $is_footer ) {
			return self::footer_html();
		}
		return $html;
	}

	/* ---------------------------------------------------------------------
	 * Piezas
	 * ------------------------------------------------------------------ */

	public static function logo_html( $class = '' ) {
		return '<a class="vp-logo ' . esc_attr( $class ) . '" href="' . esc_url( home_url( '/' ) ) . '" aria-label="VitalPeptides, inicio">'
			. VP_Shop_Icons::mark()
			. '<span class="vp-logo__word">Vital<span>·</span>Peptides</span></a>';
	}

	private static function nav_items() {
		return array(
			array( 'Catálogo', vp_shop_storefront( 'productos' ), true ),
			array( 'Rastrear pedido', vp_shop_page_url( 'rastrear-pedido' ), false ),
			array( 'Reseñas', vp_shop_page_url( 'resenas' ), false ),
			array( 'Verificar CoA', vp_shop_storefront( 'verificar-lote' ), true ),
			array( 'Mi cuenta', self::account_url(), false ),
		);
	}

	private static function account_url() {
		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
	}

	private static function cart_url() {
		return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
	}

	private static function cart_count() {
		if ( function_exists( 'WC' ) && WC()->cart instanceof WC_Cart ) {
			return (int) WC()->cart->get_cart_contents_count();
		}
		return 0;
	}

	private static function local_page_url( $slug ) {
		$p = get_page_by_path( $slug, OBJECT, 'page' );
		return $p ? get_permalink( $p ) : home_url( '/' . $slug . '/' );
	}

	public static function header_html() {
		$count   = self::cart_count();
		$current = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$wa      = vp_shop_wa_url( 'Hola VitalPeptides, tengo una consulta sobre mi pedido.' );

		ob_start();
		?>
		<header class="vp-header">
			<div class="vp-utility">
				<div class="vp-container">
					<div class="vp-utility__left">
						<a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo VP_Shop_Icons::svg( 'whatsapp' ); ?><span>Contactar a soporte</span></a>
						<span class="vp-sep" aria-hidden="true"></span>
						<a href="mailto:<?php echo esc_attr( VP_SHOP_EMAIL ); ?>"><?php echo VP_Shop_Icons::svg( 'mail' ); ?><span><?php echo esc_html( VP_SHOP_EMAIL ); ?></span></a>
						<span class="vp-sep vp-sep--lg" aria-hidden="true"></span>
						<span class="vp-utility__hours"><?php echo VP_Shop_Icons::svg( 'clock' ); ?><span>Lun–Vie 9:00–19:00 · Sáb 10:00–14:00 (CDMX)</span></span>
					</div>
					<div class="vp-utility__right">
						<span class="vp-utility__promo">Envío gratis +$2,000 MXN</span>
						<span class="vp-sep" aria-hidden="true"></span>
						<span><?php echo VP_Shop_Icons::svg( 'lock' ); ?><span>Pago seguro · Clip · SPEI</span></span>
					</div>
				</div>
			</div>
			<div class="vp-navbar">
				<div class="vp-container">
					<?php echo self::logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<nav class="vp-nav" id="vp-nav" aria-label="Principal">
						<ul>
							<?php foreach ( self::nav_items() as $item ) :
								$path    = (string) wp_parse_url( $item[1], PHP_URL_PATH );
								$is_here = ! $item[2] && '' !== $path && '/' !== $path && 0 === strpos( $current, $path );
								?>
								<li><a class="vp-nav__link<?php echo $is_here ? ' is-current' : ''; ?>" href="<?php echo esc_url( $item[1] ); ?>"<?php echo $is_here ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item[0] ); ?><?php echo $item[2] ? VP_Shop_Icons::svg( 'external', 'vp-nav__ext' ) : ''; ?></a></li>
							<?php endforeach; ?>
						</ul>
					</nav>
					<div class="vp-header__actions">
						<a class="vp-cart-btn" href="<?php echo esc_url( self::cart_url() ); ?>" aria-label="Carrito, <?php echo (int) $count; ?> artículos">
							<?php echo VP_Shop_Icons::svg( 'cart' ); ?>
							<span class="vp-cart-btn__count<?php echo $count > 0 ? ' has-items' : ''; ?>"><?php echo (int) $count; ?></span>
						</a>
						<button type="button" class="vp-burger" aria-expanded="false" aria-controls="vp-nav" aria-label="Abrir menú">
							<?php echo VP_Shop_Icons::svg( 'menu', 'vp-burger__open' ); ?><?php echo VP_Shop_Icons::svg( 'x', 'vp-burger__close' ); ?>
						</button>
					</div>
				</div>
			</div>
		</header>
		<?php
		return ob_get_clean();
	}

	public static function checkout_header_html() {
		$wa = vp_shop_wa_url( 'Hola VitalPeptides, tengo una duda al pagar mi pedido.' );
		ob_start();
		?>
		<header class="vp-header vp-header--checkout">
			<div class="vp-navbar">
				<div class="vp-container">
					<?php echo self::logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<p class="vp-secure"><?php echo VP_Shop_Icons::svg( 'lock' ); ?><span>Pago seguro</span></p>
					<a class="vp-help-link" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo VP_Shop_Icons::svg( 'whatsapp' ); ?><span>¿Ayuda?</span></a>
				</div>
			</div>
		</header>
		<?php
		return ob_get_clean();
	}

	public static function footer_html() {
		$wa = vp_shop_wa_url( 'Hola VitalPeptides, tengo una consulta.' );
		ob_start();
		?>
		<footer class="vp-footer">
			<div class="vp-container vp-footer__grid">
				<div class="vp-footer__brand">
					<?php echo self::logo_html( 'vp-logo--footer' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<p>Péptidos de investigación de alta pureza, distribuidos desde México con certificación por lote, CFDI 4.0 y envío nacional asegurado a todo el país.</p>
				</div>
				<div>
					<h3>Tienda</h3>
					<ul>
						<li><a href="<?php echo esc_url( vp_shop_storefront( 'productos' ) ); ?>">Catálogo completo</a></li>
						<li><a href="<?php echo esc_url( vp_shop_storefront( 'kits' ) ); ?>">Kits &amp; combos</a></li>
						<li><a href="<?php echo esc_url( vp_shop_storefront( 'pagos' ) ); ?>">Cómo pagar</a></li>
						<li><a href="<?php echo esc_url( vp_shop_storefront( 'envios-y-devoluciones' ) ); ?>">Envíos y devoluciones</a></li>
						<li><a href="<?php echo esc_url( vp_shop_storefront( 'garantia' ) ); ?>">Garantía</a></li>
						<li><a href="<?php echo esc_url( vp_shop_storefront( 'recomienda' ) ); ?>">Recomienda y gana $250</a></li>
					</ul>
				</div>
				<div>
					<h3>Tu pedido</h3>
					<ul>
						<li><a href="<?php echo esc_url( vp_shop_page_url( 'rastrear-pedido' ) ); ?>">Rastrear pedido</a></li>
						<li><a href="<?php echo esc_url( self::account_url() ); ?>">Mi cuenta</a></li>
						<li><a href="<?php echo esc_url( self::cart_url() ); ?>">Carrito</a></li>
						<li><a href="<?php echo esc_url( vp_shop_page_url( 'resenas' ) ); ?>">Reseñas verificadas</a></li>
						<li><a href="<?php echo esc_url( self::local_page_url( 'politica-de-devoluciones-y-reembolsos' ) ); ?>">Devoluciones y reembolsos</a></li>
					</ul>
				</div>
				<div>
					<h3>Documentación</h3>
					<ul>
						<li><a href="<?php echo esc_url( vp_shop_storefront( 'pureza' ) ); ?>">¿Qué significa pureza ≥99%?</a></li>
						<li><a href="<?php echo esc_url( vp_shop_storefront( 'verificar-lote' ) ); ?>">Verificar CoA por lote</a></li>
						<li><a href="<?php echo esc_url( vp_shop_storefront( 'lotes' ) ); ?>">Archivo de lotes</a></li>
						<li><a href="<?php echo esc_url( vp_shop_storefront( 'calculadora' ) ); ?>">Calculadora de reconstitución</a></li>
						<li><a href="<?php echo esc_url( vp_shop_storefront( 'faq' ) ); ?>">Preguntas frecuentes</a></li>
					</ul>
				</div>
				<div>
					<h3>Contacto</h3>
					<ul class="vp-footer__contact">
						<li><?php echo VP_Shop_Icons::svg( 'mail' ); ?><a href="mailto:<?php echo esc_attr( VP_SHOP_EMAIL ); ?>"><?php echo esc_html( VP_SHOP_EMAIL ); ?></a></li>
						<li><?php echo VP_Shop_Icons::svg( 'whatsapp' ); ?><a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">WhatsApp <?php echo esc_html( VP_SHOP_WA_TEXTO ); ?></a></li>
						<li><?php echo VP_Shop_Icons::svg( 'map-pin' ); ?><span>Ciudad de México</span></li>
						<li><?php echo VP_Shop_Icons::svg( 'clock' ); ?><span>Lun–Vie 9:00–19:00 · Sáb 10:00–14:00</span></li>
					</ul>
				</div>
			</div>

			<div class="vp-footer__pay">
				<div class="vp-container">
					<div class="vp-footer__pay-group">
						<span class="vp-footer__pay-label"><?php echo VP_Shop_Icons::svg( 'lock' ); ?> Métodos de pago</span>
						<ul class="vp-paylogos" aria-label="Métodos de pago aceptados">
							<li><img src="<?php echo esc_url( vp_shop_asset( 'img/payments/visa.svg' ) ); ?>" alt="Visa" width="48" height="20" loading="lazy"></li>
							<li><img src="<?php echo esc_url( vp_shop_asset( 'img/payments/mastercard.svg' ) ); ?>" alt="Mastercard" width="48" height="20" loading="lazy"></li>
							<li><img src="<?php echo esc_url( vp_shop_asset( 'img/payments/amex.svg' ) ); ?>" alt="American Express" width="48" height="20" loading="lazy"></li>
							<li class="vp-paylogos__text">SPEI</li>
						</ul>
					</div>
					<div class="vp-footer__pay-group">
						<span class="vp-footer__pay-label"><?php echo VP_Shop_Icons::svg( 'truck' ); ?> Envíos con</span>
						<ul class="vp-paylogos" aria-label="Paqueterías">
							<li><img src="<?php echo esc_url( vp_shop_asset( 'img/shipping/dhl.svg' ) ); ?>" alt="DHL" width="56" height="20" loading="lazy"></li>
							<li><img src="<?php echo esc_url( vp_shop_asset( 'img/shipping/fedex.svg' ) ); ?>" alt="FedEx" width="56" height="20" loading="lazy"></li>
							<li><img src="<?php echo esc_url( vp_shop_asset( 'img/shipping/estafeta.svg' ) ); ?>" alt="Estafeta" width="56" height="20" loading="lazy"></li>
						</ul>
					</div>
				</div>
			</div>

			<div class="vp-footer__legal">
				<div class="vp-container"><?php echo VP_Shop_Resenas::legal_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			</div>

			<div class="vp-footer__bottom">
				<div class="vp-container">
					<p>© <?php echo esc_html( wp_date( 'Y' ) ); ?> VitalPeptides · Fundado en Ciudad de México · Productos para uso exclusivo en investigación (RUO).</p>
					<p>
						<a href="<?php echo esc_url( self::local_page_url( 'terminos-y-condiciones' ) ); ?>">Términos y Condiciones</a>
						<span aria-hidden="true">·</span>
						<a href="<?php echo esc_url( self::local_page_url( 'aviso-de-privacidad' ) ); ?>">Aviso de Privacidad</a>
						<span aria-hidden="true">·</span>
						<a href="<?php echo esc_url( vp_shop_storefront( 'legal' ) ); ?>">Aviso legal</a>
					</p>
				</div>
			</div>
		</footer>
		<?php
		return ob_get_clean();
	}
}
