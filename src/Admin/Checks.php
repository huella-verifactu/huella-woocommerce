<?php
/**
 * Shop settings that make registrations fail or drift, checked on the settings page and in
 * admin notices (SPEC 6.4, 6.10).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Admin;

final class Checks {

	/**
	 * WP Overnight writes its defaults on the first visit to its settings; until then (or if the
	 * shop turned it off) no invoice is ever created, so nothing is registered.
	 */
	public static function invoices_enabled(): bool {
		$settings = get_option( 'wpo_wcpdf_documents_settings_invoice', array() );
		return is_array( $settings ) && ! empty( $settings['enabled'] );
	}

	/**
	 * WooCommerce rounds tax per line by default. The registration is still right (it sends what
	 * the invoice shows; core 0.2 allows it), but rounding at subtotal level matches the AEAT's
	 * own arithmetic exactly (decided 16 Oct, SPEC 3.3).
	 */
	public static function rounds_tax_per_line(): bool {
		return 'yes' === get_option( 'woocommerce_calc_taxes' ) && 'yes' !== get_option( 'woocommerce_tax_round_at_subtotal' );
	}

	/**
	 * @return list<string>
	 */
	public static function warnings(): array {
		$warnings = array();
		if ( ! self::invoices_enabled() ) {
			$warnings[] = __( 'PDF Invoices & Packing Slips no tiene la factura activada, así que no se crea ninguna factura y no se registra nada. Actívala en WooCommerce → PDF Invoices → Documentos → Factura.', 'huella-verifactu' );
		}
		if ( self::rounds_tax_per_line() ) {
			$warnings[] = __( 'WooCommerce redondea el IVA por línea. Los registros son correctos igualmente, pero recomendamos activar «Redondear impuesto en el subtotal, en lugar de por línea» (WooCommerce → Ajustes → Impuestos) para que cuadren al céntimo con el cálculo de la AEAT.', 'huella-verifactu' );
		}
		return $warnings;
	}
}
