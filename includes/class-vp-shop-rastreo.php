<?php
/**
 * Rastreo de pedidos.
 *
 * Página «Rastrear pedido» ([vp_rastreo]), endpoint POST /wp-json/vp/v1/rastreo
 * y la misma caja de estado en la página de gracias y en «Mi cuenta → Pedido».
 *
 * DE DÓNDE SALE CADA DATO
 * - Estado y fechas: el propio pedido de WooCommerce.
 * - Guía y paquetería: las notas del pedido. Planeta Envíos escribe
 *   «Guía de envío creada - DHL Express Domestic - Tracking: 4576356094» como
 *   nota PRIVADA (add_order_note sin segundo argumento), y el portal escribe
 *   «Envío generado / Guía: … / Paquetería: …». Por eso se leen TODAS las
 *   notas, no sólo las de cliente, y se reutiliza el parser de
 *   vp-shipping-notify cuando ese mu-plugin está cargado (siempre lo está en
 *   producción; aquí hay una copia por si se prueba en otro sitio).
 * - Liga de pago pendiente: `_vp_clip_payment_url` (vp-clip-link) o, si no
 *   existe, la URL nativa de «pagar pedido» de WooCommerce.
 * - CLABE para SPEI: la cuenta configurada en WooCommerce → Pagos → Transferencia
 *   (opción woocommerce_bacs_accounts), igual que vp-spei-emails.
 *
 * SEGURIDAD
 * - Sin sesión: se exige número de pedido + correo de facturación exactos.
 * - Lo que se muestra: nombre de pila, ciudad y estado, artículos, total, guía.
 *   Nunca la dirección completa, el teléfono ni el RFC.
 * - Límite de 20 consultas por IP cada 10 minutos (transient).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VP_Shop_Rastreo {

	const RL_MAX    = 20;
	const RL_WINDOW = 600;

	/** Pedidos ya pintados en esta petición (evita cajas duplicadas). */
	private static $printed = array();

	public static function init() {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		add_shortcode( 'vp_rastreo', array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'box_thankyou' ), 5 );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'box_order' ), 5 );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu' ) );
		add_filter( 'woocommerce_get_endpoint_url', array( __CLASS__, 'account_menu_url' ), 10, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Página y formulario
	 * ------------------------------------------------------------------ */

	public static function shortcode() {
		self::assets();

		$pedido = isset( $_GET['pedido'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_GET['pedido'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$correo = isset( $_GET['correo'] ) ? sanitize_email( (string) wp_unslash( $_GET['correo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$inicial = '';
		if ( '' !== $pedido && is_email( $correo ) ) {
			$order   = self::find_order( (int) $pedido, $correo );
			$inicial = $order ? self::render( $order ) : self::error_html( 'No encontramos un pedido con ese número y ese correo. Revisa el correo de confirmación: el número aparece en el asunto.' );
		}

		ob_start();
		?>
		<section class="vp-section vp-rastreo alignfull">
			<div class="vp-container vp-rastreo__grid">
				<div class="vp-rastreo__intro">
					<p class="vp-eyebrow">Seguimiento de pedido</p>
					<h1 class="vp-h1">¿Dónde va tu pedido?</h1>
					<p class="vp-lead">Escribe el número de pedido y el correo con el que compraste. Te decimos si el pago ya está confirmado, cuándo salió y con qué guía viaja.</p>
					<ul class="vp-hints">
						<li><?php echo VP_Shop_Icons::svg( 'mail' ); ?><span>El número viene en el asunto del correo de confirmación, por ejemplo <strong>«Pedido #920»</strong>.</span></li>
						<li><?php echo VP_Shop_Icons::svg( 'bank' ); ?><span>Si pagaste por SPEI y todavía no transfieres, aquí vuelves a ver la CLABE y la referencia.</span></li>
						<li><?php echo VP_Shop_Icons::svg( 'user' ); ?><span>¿Compraste con cuenta? En <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">Mi cuenta</a> ves todos tus pedidos.</span></li>
					</ul>
				</div>

				<div class="vp-card vp-rastreo__panel">
					<form class="vp-rastreo__form" method="get" action="<?php echo esc_url( vp_shop_page_url( 'rastrear-pedido' ) ); ?>" novalidate>
						<div class="vp-field">
							<label for="vp-pedido">Número de pedido</label>
							<input id="vp-pedido" name="pedido" type="text" inputmode="numeric" autocomplete="off" placeholder="920" required value="<?php echo esc_attr( $pedido ); ?>">
						</div>
						<div class="vp-field">
							<label for="vp-correo">Correo de la compra</label>
							<input id="vp-correo" name="correo" type="email" autocomplete="email" placeholder="tu@correo.com" required value="<?php echo esc_attr( $correo ); ?>">
						</div>
						<button type="submit" class="vp-btn vp-btn--primary vp-btn--block">
							<?php echo VP_Shop_Icons::svg( 'search' ); ?><span>Ver estado del pedido</span>
						</button>
						<p class="vp-form-note"><?php echo VP_Shop_Icons::svg( 'lock' ); ?> Sólo se muestra la información a quien tiene el número y el correo del pedido.</p>
					</form>
					<div class="vp-rastreo__result" aria-live="polite"><?php echo $inicial; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				</div>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function assets() {
		wp_enqueue_script( 'vp-shop-rastreo', vp_shop_asset( 'js/rastreo.js' ), array(), VP_SHOP_VERSION, true );
		wp_localize_script(
			'vp-shop-rastreo',
			'VP_RASTREO',
			array(
				'url'   => esc_url_raw( rest_url( 'vp/v1/rastreo' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * REST
	 * ------------------------------------------------------------------ */

	public static function rest() {
		register_rest_route(
			'vp/v1',
			'/rastreo',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_cb' ),
				'args'                => array(
					'pedido' => array( 'required' => true, 'type' => 'string' ),
					'correo' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);
	}

	public static function rest_cb( WP_REST_Request $req ) {
		if ( ! self::rate_ok() ) {
			return self::rest_error( 'demasiados_intentos', 'Demasiados intentos. Espera unos minutos y vuelve a probar.', 429 );
		}
		$pedido = preg_replace( '/\D+/', '', (string) $req->get_param( 'pedido' ) );
		$correo = sanitize_email( trim( (string) $req->get_param( 'correo' ) ) );
		if ( '' === $pedido || ! is_email( $correo ) ) {
			return self::rest_error( 'datos', 'Escribe el número de pedido y el correo con el que compraste.', 400 );
		}
		$order = self::find_order( (int) $pedido, $correo );
		if ( ! $order ) {
			return self::rest_error( 'no_encontrado', 'No encontramos un pedido con ese número y ese correo. Revisa el correo de confirmación: el número aparece en el asunto.', 404 );
		}
		$res = rest_ensure_response(
			array(
				'ok'     => true,
				'estado' => $order->get_status(),
				'html'   => self::render( $order ),
			)
		);
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	private static function rest_error( $code, $mensaje, $status ) {
		$res = new WP_REST_Response(
			array(
				'ok'      => false,
				'code'    => $code,
				'mensaje' => $mensaje,
				'html'    => self::error_html( $mensaje ),
			),
			$status
		);
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	private static function rate_ok() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0';
		$key = 'vp_rastreo_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= self::RL_MAX ) {
			return false;
		}
		set_transient( $key, $n + 1, self::RL_WINDOW );
		return true;
	}

	/** @return WC_Order|null */
	public static function find_order( $id, $correo ) {
		if ( $id <= 0 ) {
			return null;
		}
		$order = wc_get_order( $id );
		if ( ! $order instanceof WC_Order ) {
			return null;
		}
		if ( 'checkout-draft' === $order->get_status() ) {
			return null;
		}
		if ( strtolower( trim( (string) $order->get_billing_email() ) ) !== strtolower( trim( (string) $correo ) ) ) {
			return null;
		}
		return $order;
	}

	/* ---------------------------------------------------------------------
	 * Cajas en gracias y en Mi cuenta
	 * ------------------------------------------------------------------ */

	public static function box_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			self::box( $order, 'Seguimiento de tu pedido' );
		}
	}

	public static function box_order( $order ) {
		if ( $order instanceof WC_Order && ( is_account_page() || is_wc_endpoint_url( 'view-order' ) ) ) {
			self::box( $order, 'Estado del envío' );
		}
	}

	private static function box( WC_Order $order, $titulo ) {
		$id = $order->get_id();
		if ( isset( self::$printed[ $id ] ) ) {
			return;
		}
		self::$printed[ $id ] = true;
		echo '<section class="vp-rastreo-box"><h2 class="vp-h3">' . esc_html( $titulo ) . '</h2>' . self::render( $order ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function account_menu( $items ) {
		$out = array();
		foreach ( $items as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'orders' === $k ) {
				$out['vp-rastreo'] = 'Rastrear un pedido';
			}
		}
		if ( ! isset( $out['vp-rastreo'] ) ) {
			$out['vp-rastreo'] = 'Rastrear un pedido';
		}
		return $out;
	}

	public static function account_menu_url( $url, $endpoint ) {
		return 'vp-rastreo' === $endpoint ? vp_shop_page_url( 'rastrear-pedido' ) : $url;
	}

	/* ---------------------------------------------------------------------
	 * Datos del pedido
	 * ------------------------------------------------------------------ */

	public static function datos( WC_Order $order ) {
		$status = $order->get_status();
		$metodo = (string) $order->get_payment_method();
		$guia   = self::guia( $order );
		$pagado = (bool) $order->get_date_paid() || in_array( $status, array( 'processing', 'completed' ), true );

		$ciudad = $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city();
		$estado = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();

		$d = array(
			'id'        => $order->get_id(),
			'numero'    => (string) $order->get_order_number(),
			'status'    => $status,
			'fecha'     => self::fecha( $order->get_date_created(), 'j \d\e F \d\e Y' ),
			'nombre'    => (string) $order->get_billing_first_name(),
			'destino'   => trim( implode( ', ', array_filter( array( (string) $ciudad, self::estado_nombre( (string) $estado ) ) ) ) ),
			'total'     => wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			'metodo'    => (string) $order->get_payment_method_title(),
			'pagado'    => $pagado,
			'guia'      => $guia,
			'articulos' => array(),
			'pago_url'  => '',
			'spei'      => null,
			'titulo'    => '',
			'tono'      => 'info',
			'mensaje'   => '',
			'hechos'    => 1,
			'actual'    => 1,
		);

		foreach ( $order->get_items() as $item ) {
			$p   = $item->get_product();
			$img = '';
			if ( $p ) {
				$img = wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' );
				if ( ! $img && $p->get_parent_id() ) {
					$parent = wc_get_product( $p->get_parent_id() );
					$img    = $parent ? wp_get_attachment_image_url( $parent->get_image_id(), 'woocommerce_thumbnail' ) : '';
				}
			}
			$d['articulos'][] = array(
				'nombre'   => (string) $item->get_name(),
				'cantidad' => (int) $item->get_quantity(),
				'imagen'   => $img ? $img : '',
			);
		}

		switch ( $status ) {
			case 'pending':
			case 'failed':
				$d['titulo']  = 'pending' === $status ? 'Pago pendiente' : 'El pago no se completó';
				$d['tono']    = 'warn';
				$d['mensaje'] = 'Tu pedido quedó registrado pero todavía no recibimos el pago. Puedes pagar ahora con tarjeta; si pasan 24 horas sin pago, el pedido se cancela solo y liberamos las piezas.';
				$url          = (string) $order->get_meta( '_vp_clip_payment_url' );
				$d['pago_url'] = '' !== $url ? $url : $order->get_checkout_payment_url();
				$d['hechos']  = 1;
				$d['actual']  = 1;
				break;

			case 'on-hold':
				$d['hechos'] = 1;
				$d['actual'] = 1;
				if ( 'bacs' === $metodo ) {
					$d['titulo']  = 'Esperando tu transferencia SPEI';
					$d['tono']    = 'warn';
					$d['mensaje'] = 'Tu pedido está apartado. En cuanto veamos la transferencia lo confirmamos por correo y pasa a preparación. Usa el número de pedido como referencia.';
					$d['spei']    = self::spei( $order );
				} else {
					$d['titulo']  = 'Confirmando tu pago';
					$d['tono']    = 'info';
					$d['mensaje'] = 'Estamos validando el pago. Te avisamos por correo en cuanto quede confirmado.';
				}
				break;

			case 'processing':
				if ( $guia ) {
					$d['titulo']  = 'Enviado';
					$d['tono']    = 'ok';
					$d['mensaje'] = 'Tu paquete ya está con la paquetería. Rastrea la guía para ver la fecha estimada de entrega.';
					$d['hechos']  = 4;
					$d['actual']  = 3;
				} else {
					$d['titulo']  = 'Pago confirmado · preparando tu envío';
					$d['tono']    = 'ok';
					$d['mensaje'] = 'Estamos empacando tu pedido. Cuando salga, aquí aparece la guía y recibes un correo con la liga de rastreo. Normalmente sale el mismo día o el siguiente día hábil.';
					$d['hechos']  = 2;
					$d['actual']  = 2;
				}
				break;

			case 'completed':
				$d['tono']    = 'ok';
				$d['hechos']  = 4;
				$d['actual']  = 3;
				$d['titulo']  = $guia ? 'Enviado' : 'Completado';
				$d['mensaje'] = $guia
					? 'Tu paquete viaja con la paquetería. Si ya lo recibiste, ¡gracias por tu compra!'
					: 'Tu pedido está marcado como completado. Si no recibiste la guía por correo, escríbenos y te la mandamos.';
				break;

			case 'cancelled':
				$d['titulo']  = 'Cancelado';
				$d['tono']    = 'off';
				$d['mensaje'] = 'Este pedido se canceló, normalmente por falta de pago en 24 horas. Si todavía quieres los productos, vuelve a hacer el pedido desde el catálogo.';
				$d['hechos']  = 1;
				$d['actual']  = -1;
				break;

			case 'refunded':
				$d['titulo']  = 'Reembolsado';
				$d['tono']    = 'off';
				$d['mensaje'] = 'Este pedido fue reembolsado. El banco puede tardar hasta 10 días hábiles en reflejarlo.';
				$d['hechos']  = 1;
				$d['actual']  = -1;
				break;

			default:
				$d['titulo']  = wc_get_order_status_name( $status );
				$d['tono']    = 'info';
				$d['mensaje'] = 'Si tienes dudas sobre este pedido, escríbenos por WhatsApp con el número de pedido.';
				$d['hechos']  = $pagado ? 2 : 1;
				$d['actual']  = $pagado ? 2 : 1;
		}

		$d['pasos'] = array(
			array( 'titulo' => 'Pedido recibido', 'detalle' => self::fecha( $order->get_date_created(), 'j \d\e M' ) ),
			array( 'titulo' => 'Pago confirmado', 'detalle' => $pagado ? self::fecha( $order->get_date_paid(), 'j \d\e M' ) : ( 'bacs' === $metodo ? 'Esperando SPEI' : 'Pendiente' ) ),
			array( 'titulo' => 'En preparación', 'detalle' => $pagado ? ( $guia ? 'Listo' : 'En curso' ) : '' ),
			array( 'titulo' => 'Enviado', 'detalle' => $guia ? trim( $guia['paqueteria'] . ( $guia['fecha'] ? ' · ' . $guia['fecha'] : '' ) ) : '' ),
		);

		return $d;
	}

	/** @return array|null  numero, paqueteria, servicio, url, fecha, logo */
	public static function guia( WC_Order $order ) {
		$notes = wc_get_order_notes(
			array(
				'order_id' => $order->get_id(),
				'orderby'  => 'date_created',
				'order'    => 'DESC',
			)
		);
		$canceladas = array();
		$found      = null;
		foreach ( $notes as $n ) {
			$t = wp_strip_all_tags( (string) $n->content );
			if ( preg_match( '/cancel/iu', $t ) ) {
				if ( preg_match( '/Tracking\s*:\s*([A-Za-z0-9\-]{6,40})/iu', $t, $m ) ) {
					$canceladas[ strtoupper( $m[1] ) ] = true;
				}
				continue;
			}
			$p = self::parse_note( $t );
			if ( ! $p || isset( $canceladas[ strtoupper( $p['tracking'] ) ] ) ) {
				continue;
			}
			$found          = $p;
			$found['fecha'] = self::fecha( $n->date_created, 'j \d\e M' );
			break;
		}
		if ( ! $found ) {
			return null;
		}
		$url = class_exists( 'VP_Shipping_Notify' )
			? VP_Shipping_Notify::tracking_url( $found['carrier'], $found['tracking'] )
			: self::tracking_url( $found['carrier'], $found['tracking'] );

		return array(
			'numero'     => $found['tracking'],
			'paqueteria' => '' !== $found['carrier'] ? $found['carrier'] : 'Paquetería',
			'servicio'   => $found['service'],
			'url'        => $url,
			'fecha'      => $found['fecha'],
			'logo'       => self::carrier_logo( $found['carrier'] ),
		);
	}

	/** Copia del parser de vp-shipping-notify, por si ese mu-plugin no está. */
	public static function parse_note( $text ) {
		if ( class_exists( 'VP_Shipping_Notify' ) ) {
			return VP_Shipping_Notify::parse_note( $text );
		}
		$t = str_replace( array( "\r\n", "\r" ), "\n", wp_strip_all_tags( (string) $text ) );
		if ( '' === trim( $t ) || preg_match( '/cancel/iu', $t ) ) {
			return false;
		}
		$tracking = '';
		$carrier  = '';
		$service  = '';
		if ( preg_match( '/Gu[ií]a\s*:\s*([A-Za-z0-9\-]{6,40})/u', $t, $m ) ) {
			$tracking = $m[1];
		}
		if ( '' === $tracking && preg_match( '/Tracking\s*:\s*([A-Za-z0-9\-]{6,40})/iu', $t, $m ) ) {
			$tracking = $m[1];
		}
		if ( '' === $tracking ) {
			return false;
		}
		if ( preg_match( '/Paqueter[ií]a\s*:\s*([^\n]+)/u', $t, $m ) ) {
			$carrier = trim( $m[1] );
		}
		if ( preg_match( '/Servicio\s*:\s*([^\n]+)/u', $t, $m ) ) {
			$service = trim( $m[1] );
		}
		if ( '' === $carrier && preg_match( '/creada\s*-\s*(\S+)\s*(.*?)\s*-\s*Tracking/iu', $t, $m ) ) {
			$carrier = trim( $m[1] );
			$service = trim( $m[2] );
		}
		return array(
			'tracking' => $tracking,
			'carrier'  => $carrier,
			'service'  => $service,
		);
	}

	public static function tracking_url( $carrier, $tracking ) {
		$c = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $carrier ) );
		$t = rawurlencode( $tracking );
		$map = array(
			'DHL'           => 'https://www.dhl.com/mx-es/home/rastreo.html?tracking-id=' . $t,
			'FEDEX'         => 'https://www.fedex.com/fedextrack/?trknbr=' . $t,
			'ESTAFETA'      => 'https://www.estafeta.com/Rastreo/' . $t,
			'PAQUETEXPRESS' => 'https://www.paquetexpress.com.mx/rastreo/' . $t,
			'UPS'           => 'https://www.ups.com/track?tracknum=' . $t,
			'REDPACK'       => 'https://www.redpack.com.mx/rastreo/' . $t,
			'AMPM'          => 'https://www.ampm.com.mx/rastreo/' . $t,
			'IMILE'         => 'https://www.imile.com/track/' . $t,
		);
		return isset( $map[ $c ] ) ? $map[ $c ] : '';
	}

	private static function carrier_logo( $carrier ) {
		$c = strtolower( preg_replace( '/[^A-Za-z]/', '', (string) $carrier ) );
		if ( in_array( $c, array( 'dhl', 'fedex', 'estafeta' ), true ) ) {
			return vp_shop_asset( 'img/shipping/' . $c . '.svg' );
		}
		return '';
	}

	private static function spei( WC_Order $order ) {
		$cuentas = (array) get_option( 'woocommerce_bacs_accounts', array() );
		$c       = reset( $cuentas );
		$c       = is_array( $c ) ? $c : array();
		return array(
			'banco'      => isset( $c['bank_name'] ) ? (string) $c['bank_name'] : '',
			'titular'    => isset( $c['account_name'] ) ? (string) $c['account_name'] : '',
			'clabe'      => isset( $c['account_number'] ) ? (string) $c['account_number'] : '',
			'referencia' => (string) $order->get_order_number(),
			'monto'      => wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
		);
	}

	private static function fecha( $dt, $formato = 'j \d\e F' ) {
		if ( ! $dt instanceof DateTimeInterface ) {
			return '';
		}
		return wp_date( $formato, $dt->getTimestamp() );
	}

	private static function estado_nombre( $code ) {
		if ( '' === $code || ! function_exists( 'WC' ) || ! WC()->countries ) {
			return $code;
		}
		$states = WC()->countries->get_states( 'MX' );
		return isset( $states[ $code ] ) ? $states[ $code ] : $code;
	}

	/* ---------------------------------------------------------------------
	 * HTML
	 * ------------------------------------------------------------------ */

	public static function error_html( $mensaje ) {
		return '<div class="vp-alert vp-alert--warn" role="alert">' . VP_Shop_Icons::svg( 'help' ) . '<div><p>' . esc_html( $mensaje ) . '</p><p class="vp-alert__cta"><a href="' . esc_url( vp_shop_wa_url( 'Hola VitalPeptides, no encuentro mi pedido en el rastreador.' ) ) . '" target="_blank" rel="noopener">Escríbenos por WhatsApp</a> y lo buscamos contigo.</p></div></div>';
	}

	public static function render( WC_Order $order ) {
		$d = self::datos( $order );
		ob_start();
		?>
		<article class="vp-pedido vp-pedido--<?php echo esc_attr( $d['tono'] ); ?>">
			<header class="vp-pedido__head">
				<div>
					<p class="vp-eyebrow">Pedido #<?php echo esc_html( $d['numero'] ); ?> · <?php echo esc_html( $d['fecha'] ); ?></p>
					<h2 class="vp-pedido__titulo"><?php echo esc_html( $d['titulo'] ); ?></h2>
					<?php if ( '' !== $d['nombre'] ) : ?>
						<p class="vp-pedido__hola">Hola, <?php echo esc_html( $d['nombre'] ); ?>.<?php echo '' !== $d['destino'] ? ' Destino: ' . esc_html( $d['destino'] ) . '.' : ''; ?></p>
					<?php endif; ?>
				</div>
				<span class="vp-pill vp-pill--<?php echo esc_attr( $d['tono'] ); ?>"><?php echo esc_html( $d['titulo'] ); ?></span>
			</header>

			<ol class="vp-timeline<?php echo $d['actual'] < 0 ? ' vp-timeline--off' : ''; ?>">
				<?php foreach ( $d['pasos'] as $i => $paso ) :
					$cls = $i < $d['hechos'] ? 'is-done' : '';
					if ( $i === $d['actual'] ) {
						$cls .= ' is-current';
					}
					?>
					<li class="vp-timeline__step <?php echo esc_attr( trim( $cls ) ); ?>">
						<span class="vp-timeline__dot"><?php echo $i < $d['hechos'] ? VP_Shop_Icons::svg( 'check' ) : '<i></i>'; ?></span>
						<span class="vp-timeline__label"><strong><?php echo esc_html( $paso['titulo'] ); ?></strong><?php echo '' !== $paso['detalle'] ? '<small>' . esc_html( $paso['detalle'] ) . '</small>' : ''; ?></span>
					</li>
				<?php endforeach; ?>
			</ol>

			<p class="vp-pedido__mensaje"><?php echo esc_html( $d['mensaje'] ); ?></p>

			<?php if ( $d['guia'] ) : ?>
				<div class="vp-panel vp-panel--envio">
					<div class="vp-panel__head">
						<?php echo VP_Shop_Icons::svg( 'truck' ); ?>
						<div>
							<p class="vp-panel__title">Guía de envío</p>
							<p class="vp-panel__sub"><?php echo esc_html( $d['guia']['paqueteria'] ); ?><?php echo '' !== $d['guia']['servicio'] ? ' · ' . esc_html( $d['guia']['servicio'] ) : ''; ?></p>
						</div>
						<?php if ( '' !== $d['guia']['logo'] ) : ?>
							<img class="vp-panel__logo" src="<?php echo esc_url( $d['guia']['logo'] ); ?>" alt="<?php echo esc_attr( $d['guia']['paqueteria'] ); ?>" width="72" height="28" loading="lazy">
						<?php endif; ?>
					</div>
					<p class="vp-guia"><code><?php echo esc_html( $d['guia']['numero'] ); ?></code><button type="button" class="vp-copy" data-copy="<?php echo esc_attr( $d['guia']['numero'] ); ?>" aria-label="Copiar número de guía"><?php echo VP_Shop_Icons::svg( 'copy' ); ?></button></p>
					<?php if ( '' !== $d['guia']['url'] ) : ?>
						<a class="vp-btn vp-btn--primary" href="<?php echo esc_url( $d['guia']['url'] ); ?>" target="_blank" rel="noopener">Rastrear en <?php echo esc_html( $d['guia']['paqueteria'] ); ?> <?php echo VP_Shop_Icons::svg( 'external' ); ?></a>
					<?php else : ?>
						<p class="vp-muted">Busca la guía en el sitio de <?php echo esc_html( $d['guia']['paqueteria'] ); ?>.</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $d['pago_url'] ) : ?>
				<div class="vp-panel vp-panel--pago">
					<div class="vp-panel__head">
						<?php echo VP_Shop_Icons::svg( 'credit-card' ); ?>
						<div>
							<p class="vp-panel__title">Completa tu pago</p>
							<p class="vp-panel__sub"><?php echo esc_html( $d['metodo'] ); ?> · Total <?php echo esc_html( $d['total'] ); ?></p>
						</div>
					</div>
					<a class="vp-btn vp-btn--primary" href="<?php echo esc_url( $d['pago_url'] ); ?>">Pagar ahora <?php echo VP_Shop_Icons::svg( 'arrow-right' ); ?></a>
					<p class="vp-muted">Pago seguro con Clip. Te llevamos a su página para capturar la tarjeta.</p>
				</div>
			<?php endif; ?>

			<?php if ( is_array( $d['spei'] ) && '' !== $d['spei']['clabe'] ) : ?>
				<div class="vp-panel vp-panel--spei">
					<div class="vp-panel__head">
						<?php echo VP_Shop_Icons::svg( 'bank' ); ?>
						<div>
							<p class="vp-panel__title">Datos para tu transferencia SPEI</p>
							<p class="vp-panel__sub">Monto exacto: <?php echo esc_html( $d['spei']['monto'] ); ?></p>
						</div>
					</div>
					<dl class="vp-dl">
						<?php if ( '' !== $d['spei']['banco'] ) : ?><div><dt>Banco</dt><dd><?php echo esc_html( $d['spei']['banco'] ); ?></dd></div><?php endif; ?>
						<div><dt>CLABE</dt><dd><code><?php echo esc_html( $d['spei']['clabe'] ); ?></code><button type="button" class="vp-copy" data-copy="<?php echo esc_attr( $d['spei']['clabe'] ); ?>" aria-label="Copiar CLABE"><?php echo VP_Shop_Icons::svg( 'copy' ); ?></button></dd></div>
						<?php if ( '' !== $d['spei']['titular'] ) : ?><div><dt>Beneficiario</dt><dd><?php echo esc_html( $d['spei']['titular'] ); ?></dd></div><?php endif; ?>
						<div><dt>Referencia</dt><dd><code><?php echo esc_html( $d['spei']['referencia'] ); ?></code></dd></div>
					</dl>
					<p class="vp-muted">Cuando transfieras, no hace falta avisar: conciliamos el pago y te confirmamos por correo.</p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $d['articulos'] ) ) : ?>
				<div class="vp-pedido__items">
					<p class="vp-panel__title">Lo que incluye</p>
					<ul class="vp-items">
						<?php foreach ( $d['articulos'] as $a ) : ?>
							<li>
								<?php if ( '' !== $a['imagen'] ) : ?><img src="<?php echo esc_url( $a['imagen'] ); ?>" alt="" width="48" height="48" loading="lazy"><?php else : ?><span class="vp-items__ph"><?php echo VP_Shop_Icons::svg( 'package' ); ?></span><?php endif; ?>
								<span class="vp-items__name"><?php echo esc_html( $a['nombre'] ); ?></span>
								<span class="vp-items__qty">× <?php echo (int) $a['cantidad']; ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="vp-items__total"><span>Total</span><strong><?php echo esc_html( $d['total'] ); ?></strong></p>
				</div>
			<?php endif; ?>

			<footer class="vp-pedido__help">
				<?php echo VP_Shop_Icons::svg( 'whatsapp' ); ?>
				<p>¿Algo no cuadra? <a href="<?php echo esc_url( vp_shop_wa_url( 'Hola VitalPeptides, tengo una duda sobre mi pedido #' . $d['numero'] ) ); ?>" target="_blank" rel="noopener">Escríbenos por WhatsApp</a> o a <a href="mailto:<?php echo esc_attr( VP_SHOP_EMAIL ); ?>"><?php echo esc_html( VP_SHOP_EMAIL ); ?></a> con el número de pedido.</p>
			</footer>
		</article>
		<?php
		return ob_get_clean();
	}
}
