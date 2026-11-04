<?php
/**
 * Portada de vitalpep.shop ([vp_inicio]).
 *
 * vitalpep.shop no es la tienda: es donde se paga y donde se sigue el pedido.
 * El catálogo, el CoA por lote y la calculadora viven en vitalpeptides.app.
 * La portada lo dice sin rodeos y lleva a lo único que alguien viene a hacer
 * aquí: rastrear su pedido, entrar a su cuenta o volver al catálogo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VP_Shop_Inicio {

	public static function init() {
		add_shortcode( 'vp_inicio', array( __CLASS__, 'shortcode' ) );
	}

	public static function shortcode() {
		$rastreo  = vp_shop_page_url( 'rastrear-pedido' );
		$cuenta   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
		$catalogo = vp_shop_storefront( 'productos' );
		ob_start();
		?>
		<section class="vp-section vp-hero alignfull">
			<div class="vp-container vp-hero__grid">
				<div class="vp-hero__copy">
					<p class="vp-eyebrow">VitalPeptides · México · Pagos y pedidos</p>
					<h1 class="vp-h1 vp-h1--xl">Tu pedido, de principio a fin.</h1>
					<p class="vp-lead">Aquí pagas de forma segura lo que elegiste en vitalpeptides.app y sigues tu paquete hasta la puerta: estado del pago, guía y liga de rastreo en un solo lugar.</p>
					<div class="vp-hero__cta">
						<a class="vp-btn vp-btn--primary vp-btn--lg" href="<?php echo esc_url( $rastreo ); ?>"><?php echo VP_Shop_Icons::svg( 'search' ); ?> Rastrear mi pedido</a>
						<a class="vp-link vp-link--lg" href="<?php echo esc_url( $catalogo ); ?>">Ir al catálogo <?php echo VP_Shop_Icons::svg( 'arrow-right' ); ?></a>
					</div>
					<form class="vp-hero__quick" method="get" action="<?php echo esc_url( $rastreo ); ?>">
						<label for="vp-hero-pedido" class="screen-reader-text">Número de pedido</label>
						<input id="vp-hero-pedido" name="pedido" type="text" inputmode="numeric" placeholder="Nº de pedido" autocomplete="off">
						<label for="vp-hero-correo" class="screen-reader-text">Correo de la compra</label>
						<input id="vp-hero-correo" name="correo" type="email" placeholder="Correo de la compra" autocomplete="email">
						<button type="submit" class="vp-btn vp-btn--dark">Ver estado</button>
					</form>
				</div>
				<div class="vp-hero__stage">
					<img src="<?php echo esc_url( vp_shop_asset( 'img/vial-reta-900.jpg' ) ); ?>" alt="Vial de Retatrutide 5 mg de VitalPeptides" width="771" height="900" loading="eager" fetchpriority="high">
				</div>
			</div>
		</section>

		<section class="vp-section vp-trustbar alignfull" aria-label="Garantías VitalPeptides">
			<div class="vp-container"><?php echo VP_Shop_Resenas::trust_chips_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		</section>

		<section class="vp-section vp-acciones alignfull">
			<div class="vp-container">
				<p class="vp-eyebrow">¿Qué necesitas hoy?</p>
				<h2 class="vp-h2">Tres cosas que se hacen aquí</h2>
				<div class="vp-acciones__grid">
					<a class="vp-action" href="<?php echo esc_url( $rastreo ); ?>">
						<span class="vp-action__icon"><?php echo VP_Shop_Icons::svg( 'package' ); ?></span>
						<h3 class="vp-h3">Rastrear un pedido</h3>
						<p>Con el número de pedido y tu correo ves si el pago está confirmado, cuándo salió y con qué guía viaja. Sin cuenta.</p>
						<span class="vp-link">Rastrear <?php echo VP_Shop_Icons::svg( 'arrow-right' ); ?></span>
					</a>
					<a class="vp-action" href="<?php echo esc_url( $cuenta ); ?>">
						<span class="vp-action__icon"><?php echo VP_Shop_Icons::svg( 'user' ); ?></span>
						<h3 class="vp-h3">Mi cuenta</h3>
						<p>Historial de pedidos, direcciones de envío y facturación, y datos para tu CFDI 4.0.</p>
						<span class="vp-link">Entrar <?php echo VP_Shop_Icons::svg( 'arrow-right' ); ?></span>
					</a>
					<a class="vp-action" href="<?php echo esc_url( $catalogo ); ?>">
						<span class="vp-action__icon"><?php echo VP_Shop_Icons::svg( 'flask' ); ?></span>
						<h3 class="vp-h3">Catálogo y CoA</h3>
						<p>El catálogo completo, el verificador de lote y la calculadora de reconstitución viven en vitalpeptides.app.</p>
						<span class="vp-link">Ir a vitalpeptides.app <?php echo VP_Shop_Icons::svg( 'external' ); ?></span>
					</a>
				</div>
			</div>
		</section>

		<section class="vp-section vp-pagos alignfull">
			<div class="vp-container">
				<div class="vp-pagos__intro">
					<p class="vp-eyebrow">Cómo pagar</p>
					<h2 class="vp-h2">Dos formas de pago, las dos seguras</h2>
					<p class="vp-lead">Nunca guardamos datos de tarjeta: el cobro lo procesa Clip. Si prefieres transferencia, el pedido queda apartado mientras llega tu SPEI.</p>
				</div>
				<div class="vp-pagos__grid">
					<div class="vp-card vp-pago">
						<span class="vp-action__icon"><?php echo VP_Shop_Icons::svg( 'credit-card' ); ?></span>
						<h3 class="vp-h3">Tarjeta con Clip</h3>
						<p>Visa, Mastercard y American Express, crédito o débito. Te llevamos a la página segura de Clip para capturar la tarjeta y regresas con el pedido confirmado.</p>
						<ul class="vp-paylogos" aria-label="Tarjetas aceptadas">
							<li><img src="<?php echo esc_url( vp_shop_asset( 'img/payments/visa.svg' ) ); ?>" alt="Visa" width="48" height="20" loading="lazy"></li>
							<li><img src="<?php echo esc_url( vp_shop_asset( 'img/payments/mastercard.svg' ) ); ?>" alt="Mastercard" width="48" height="20" loading="lazy"></li>
							<li><img src="<?php echo esc_url( vp_shop_asset( 'img/payments/amex.svg' ) ); ?>" alt="American Express" width="48" height="20" loading="lazy"></li>
						</ul>
					</div>
					<div class="vp-card vp-pago">
						<span class="vp-action__icon"><?php echo VP_Shop_Icons::svg( 'bank' ); ?></span>
						<h3 class="vp-h3">Transferencia SPEI</h3>
						<p>Eliges SPEI, recibes la CLABE y la referencia por correo, y transfieres desde tu banca. En cuanto conciliamos el pago, el pedido pasa a preparación.</p>
						<p class="vp-muted">La CLABE también se puede volver a consultar desde «Rastrear pedido».</p>
					</div>
				</div>
			</div>
		</section>

		<?php $resumen = VP_Shop_Resenas::resumen_html( 3 ); ?>
		<?php if ( '' !== $resumen ) : ?>
			<section class="vp-section vp-section--surface alignfull">
				<div class="vp-container"><?php echo $resumen; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			</section>
		<?php endif; ?>

		<section class="vp-section vp-ayuda alignfull">
			<div class="vp-container vp-ayuda__grid">
				<div>
					<p class="vp-eyebrow">Ayuda</p>
					<h2 class="vp-h2">Una persona del otro lado</h2>
					<p class="vp-lead">Dudas de pago, de envío o de factura: por WhatsApp en horario laboral, o por correo a cualquier hora.</p>
				</div>
				<ul class="vp-contact">
					<li><?php echo VP_Shop_Icons::svg( 'whatsapp' ); ?><a href="<?php echo esc_url( vp_shop_wa_url( 'Hola VitalPeptides, tengo una duda sobre mi pedido.' ) ); ?>" target="_blank" rel="noopener">WhatsApp <?php echo esc_html( VP_SHOP_WA_TEXTO ); ?></a></li>
					<li><?php echo VP_Shop_Icons::svg( 'mail' ); ?><a href="mailto:<?php echo esc_attr( VP_SHOP_EMAIL ); ?>"><?php echo esc_html( VP_SHOP_EMAIL ); ?></a></li>
					<li><?php echo VP_Shop_Icons::svg( 'clock' ); ?><span>Lun–Vie 9:00–19:00 · Sáb 10:00–14:00 (CDMX)</span></li>
				</ul>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
