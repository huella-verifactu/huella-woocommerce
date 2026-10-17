<?php
/**
 * Refunds in v1.0 (SPEC 6.8): a refund of a registered invoice needs a corrective invoice
 * (R1 for a full invoice, R5 for a simplified one). The plugin does not issue it yet (v1.1, or
 * WP Overnight Pro credit notes): it tells the shop, on the order and in an admin notice, and
 * does nothing else. A refund is never an anulación: the original invoice stays valid.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Woo;

use Huella\Verifactu\Admin\RefundList;
use WC_Order;

final class Refunds {

	public const OPTION  = 'huella_verifactu_refunds_pending'; // Order IDs, newest last.
	public const RESOLVE = 'huella_verifactu_refunds_resolved';

	public static function register(): void {
		add_action( 'woocommerce_order_refunded', array( self::class, 'refunded' ), 10, 1 );
		add_action( 'admin_post_' . self::RESOLVE, array( self::class, 'resolve' ) );
	}

	/**
	 * @param int|mixed $order_id The refunded order.
	 */
	public static function refunded( $order_id ): void {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof WC_Order || '' === (string) $order->get_meta( Meta::RECORD_ID ) ) {
			return; // No registered invoice: nothing to rectify in Verifactu.
		}
		$order->add_order_note( self::message() );
		update_option( self::OPTION, RefundList::add( get_option( self::OPTION, array() ), $order->get_id() ), false );
	}

	public static function message(): string {
		return __( 'Verifactu: este reembolso necesita una factura rectificativa que lo registre (R1 si la factura era completa, R5 si era simplificada). Huella todavía no la emite: hazla con tu programa de facturación o con PDF Invoices Professional. La factura original sigue siendo válida; no la anules.', 'huella-verifactu' );
	}

	/**
	 * @return list<int> Orders still waiting for a corrective invoice, as far as the shop said.
	 */
	public static function pending(): array {
		return RefundList::read( get_option( self::OPTION, array() ) );
	}

	/**
	 * "Marcar como resueltos" in the notice: the shop has issued the corrective invoices.
	 */
	public static function resolve(): void {
		check_admin_referer( self::RESOLVE );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para gestionar WooCommerce.', 'huella-verifactu' ), 403 );
		}
		delete_option( self::OPTION );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
