<?php
/**
 * Prints the AEAT QR in the WP Overnight invoice (SPEC 6.7) at the position chosen in the
 * settings. The QR depends only on NIF, number, date and total, so it prints even before the
 * registration reaches Huella (D7).
 *
 * Never breaks a PDF: if anything is missing or fails, the invoice renders without the block
 * and the reason is logged (WooCommerce → Estado → Registros, source huella-verifactu).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Integration;

use Huella\Verifactu\Money;
use Huella\Verifactu\Qr;
use Huella\Verifactu\QrImage;
use Huella\Verifactu\Woo\Meta;
use Huella\Verifactu\Woo\Options;
use Huella\Verifactu\Woo\OrderReader;
use WC_Order;

final class QrBlock {

	public const POSITIONS = array( 'after_document_label', 'after_order_details', 'before_document_notes' );

	public static function register(): void {
		$position = Options::get( 'qr_position', 'after_document_label' );
		if ( ! in_array( $position, self::POSITIONS, true ) ) {
			$position = 'after_document_label';
		}
		add_action( "wpo_wcpdf_{$position}", array( self::class, 'render' ), 10, 2 );
	}

	/**
	 * @param mixed $type  Document type.
	 * @param mixed $order The order.
	 */
	public static function render( $type, $order ): void {
		if ( 'invoice' !== $type || ! $order instanceof WC_Order ) {
			return;
		}
		try {
			echo self::html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template
		} catch ( \Throwable $error ) {
			wc_get_logger()->error( "Huella: no QR for order {$order->get_id()}: " . $error->getMessage(), array( 'source' => 'huella-verifactu' ) );
		}
	}

	/**
	 * The block's HTML, or "" when the order has no invoice or the plugin is not configured.
	 */
	public static function html( WC_Order $order ): string {
		$invoice = OrderReader::invoice( $order );
		$nif     = Options::get( 'issuer_nif' );
		if ( null === $invoice || '' === $nif ) {
			return '';
		}
		$number = $invoice->get_number();
		$date   = $invoice->get_date();
		if ( null === $number || ! $date instanceof \WC_DateTime ) {
			return '';
		}
		$qr_url   = Qr::url( $nif, $number->get_formatted(), $date->date( 'Y-m-d' ), Money::text( (string) $order->get_total( 'edit' ) ), self::environment( $order ) );
		$qr_image = QrImage::for_url( $qr_url );

		$template = locate_template( 'huella-verifactu/qr-block.php' );
		if ( '' === $template ) {
			$template = dirname( __DIR__, 2 ) . '/templates/qr-block.php';
		}
		ob_start();
		include $template;
		return (string) ob_get_clean();
	}

	/**
	 * Where the record went (stored at registration), else where it will go (the key's prefix).
	 */
	private static function environment( WC_Order $order ): string {
		$registered = (string) $order->get_meta( Meta::ENVIRONMENT );
		if ( 'live' === $registered || 'test' === $registered ) {
			return $registered;
		}
		$api = Options::api();
		return null === $api ? 'test' : $api->environment();
	}
}
