<?php
/**
 * Iconos en línea (trazo 24×24, estilo Lucide, mismos que usa vitalpeptides.app).
 * Se incrustan como SVG para no depender de fuentes de iconos ni de peticiones extra.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VP_Shop_Icons {

	/** @return string SVG listo para imprimir (ya escapado: sólo contiene atributos fijos). */
	public static function svg( $name, $class = '' ) {
		$paths = self::paths();
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		$cls = 'vp-icon' . ( '' !== $class ? ' ' . $class : '' );
		return '<svg class="' . esc_attr( $cls ) . '" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/** Estrella rellena (reseñas). */
	public static function star( $filled = true, $size = 16 ) {
		$fill = $filled ? 'currentColor' : 'none';
		$op   = $filled ? '1' : '0.35';
		return '<svg class="vp-star" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="' . $fill . '" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" style="opacity:' . $op . '" aria-hidden="true" focusable="false"><path d="M12 2.5l2.9 6.1 6.7.8-4.9 4.6 1.3 6.6L12 17.3 6 20.6l1.3-6.6L2.4 9.4l6.7-.8z"/></svg>';
	}

	/** Marca de la cadena peptídica (misma geometría que Logo.tsx del storefront). */
	public static function mark( $class = '' ) {
		return '<svg class="vp-mark ' . esc_attr( $class ) . '" viewBox="0 0 64 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M8 16 L20 16 M28 16 L36 16 M44 16 L56 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M20 16 L24 12 L28 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M36 16 L40 20 L44 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="6" cy="16" r="4" fill="currentColor"/><circle cx="58" cy="16" r="4" fill="currentColor"/><circle cx="22" cy="11" r="3" stroke="currentColor" stroke-width="2"/><circle cx="42" cy="21" r="3" stroke="currentColor" stroke-width="2"/></svg>';
	}

	private static function paths() {
		return array(
			'lock'         => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
			'truck'        => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
			'file-check'   => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/>',
			'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
			'shield-alert' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
			'package'      => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
			'package-check' => '<path d="m16 16 2 2 4-4"/><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"/><path d="m7.5 4.27 9 5.15"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
			'whatsapp'     => '<path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/>',
			'mail'         => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
			'phone'        => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
			'clock'        => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
			'check'        => '<path d="M20 6 9 17l-5-5"/>',
			'circle-check' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
			'arrow-right'  => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
			'search'       => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
			'receipt'      => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>',
			'user'         => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
			'cart'         => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
			'menu'         => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
			'x'            => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
			'external'     => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
			'credit-card'  => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
			'bank'         => '<path d="M3 10h18"/><path d="M5 10v9"/><path d="M19 10v9"/><path d="M9 10v9"/><path d="M15 10v9"/><path d="M2 20h20"/><path d="m12 3 9 5H3z"/>',
			'map-pin'      => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
			'flask'        => '<path d="M10 2v7.31"/><path d="M14 9.3V1.99"/><path d="M8.5 2h7"/><path d="M14 9.3a6.5 6.5 0 1 1-4 0"/><path d="M5.52 16h12.96"/>',
			'calculator'   => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8"/><path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/>',
			'badge-check'  => '<path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="m9 12 2 2 4-4"/>',
			'help'         => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
			'copy'         => '<rect x="8" y="8" width="14" height="14" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
		);
	}
}
