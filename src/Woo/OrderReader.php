<?php
/**
 * WC_Order + its WP Overnight invoice -> OrderSnapshot (SPEC 6.3, 6.5).
 *
 * Tax percents come from the order's own tax items (WC_Order_Item_Tax::get_rate_percent()),
 * so an order still reads correctly after the shop edits or deletes a tax rate.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Woo;

use Huella\Verifactu\Invoice\Customer;
use Huella\Verifactu\Invoice\OrderLine;
use Huella\Verifactu\Invoice\OrderSnapshot;
use WC_DateTime;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Order_Item_Tax;
use WC_Tax;

final class OrderReader {

	private const LINE_TYPES = array( 'line_item', 'shipping', 'fee' );

	/**
	 * The snapshot, or null while the order has no WP Overnight invoice.
	 */
	public static function read( WC_Order $order, string $tax_id ): ?OrderSnapshot {
		$invoice = self::invoice( $order );
		if ( null === $invoice ) {
			return null;
		}
		$number = $invoice->get_number();
		$date   = $invoice->get_date();
		return new OrderSnapshot(
			$order->get_id(),
			(string) $order->get_order_number(),
			is_object( $number ) ? $number->get_formatted() : (string) $number,
			$date instanceof WC_DateTime ? $date->date( 'Y-m-d' ) : '',
			(string) $order->get_total( 'edit' ),
			self::lines( $order ),
			self::customer( $order, $tax_id )
		);
	}

	/**
	 * The order's WP Overnight invoice, if it has been created (never creates one).
	 *
	 * @return \WPO\IPS\Documents\OrderDocument|null
	 */
	public static function invoice( WC_Order $order ) {
		if ( ! function_exists( 'wcpdf_get_document' ) ) {
			return null;
		}
		$invoice = wcpdf_get_document( 'invoice', $order );
		return $invoice && $invoice->exists() ? $invoice : null;
	}

	/**
	 * @return list<OrderLine>
	 */
	private static function lines( WC_Order $order ): array {
		$percents = array();
		foreach ( $order->get_items( 'tax' ) as $tax_item ) {
			if ( $tax_item instanceof WC_Order_Item_Tax ) {
				$percents[ $tax_item->get_rate_id() ] = (string) $tax_item->get_rate_percent();
			}
		}
		$lines = array();
		foreach ( $order->get_items( self::LINE_TYPES ) as $item ) {
			if ( $item instanceof WC_Order_Item_Product || $item instanceof WC_Order_Item_Shipping || $item instanceof WC_Order_Item_Fee ) {
				$lines[] = new OrderLine( $item->get_name(), (string) $item->get_total(), self::taxes( $item, $percents ) );
			}
		}
		return $lines;
	}

	/**
	 * Tax percent => amount of one line. Two tax rates with the same percent on one line stay
	 * two entries (suffix), so the mapper refuses the line instead of merging them silently.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $item     A line.
	 * @param array<int, string>                                           $percents Rate ID => percent, from the order's tax items.
	 * @return array<string, string>
	 */
	private static function taxes( $item, array $percents ): array {
		$taxes  = array();
		$totals = $item->get_taxes()['total'] ?? array();
		foreach ( is_array( $totals ) ? $totals : array() as $rate_id => $amount ) {
			if ( '' === (string) $amount ) {
				continue;
			}
			$percent = $percents[ $rate_id ] ?? (string) WC_Tax::get_rate_percent_value( (int) $rate_id );
			$key     = isset( $taxes[ $percent ] ) ? $percent . '#' . $rate_id : $percent;
			$taxes[ $key ] = (string) $amount;
		}
		return $taxes;
	}

	private static function customer( WC_Order $order, string $tax_id ): Customer {
		$name = trim( $order->get_billing_company() );
		if ( '' === $name ) {
			$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		}
		return new Customer( $name, $tax_id, strtoupper( $order->get_billing_country() ) );
	}
}
