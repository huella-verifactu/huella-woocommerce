<?php
/**
 * PDF Invoices & Packing Slips (WP Overnight) hooks (SPEC 6.3, Q5 answered 16 Oct).
 *
 * - wpo_wcpdf_save_document($document, $order): fires after the invoice number and date are
 *   saved; also on every later save, so this handler is idempotent. A new invoice queues a
 *   background send. On a registered order, a changed number or date is flagged: the AEAT
 *   record cannot change, so the shop needs an anulación and a new invoice.
 * - wpo_wcpdf_before_document($type, $order): safety net at render time. An invoice PDF never
 *   exists without a queued registration.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Integration;

use Huella\Verifactu\Sending\Scheduler;
use Huella\Verifactu\Sending\Status;
use Huella\Verifactu\Woo\Meta;
use Huella\Verifactu\Woo\OrderReader;
use WC_Order;

final class Wcpdf {

	/** @var Scheduler */
	private $scheduler;

	public function __construct( Scheduler $scheduler ) {
		$this->scheduler = $scheduler;
	}

	public function register(): void {
		add_action( 'wpo_wcpdf_save_document', array( $this, 'saved' ), 10, 2 );
		add_action( 'wpo_wcpdf_before_document', array( $this, 'rendering' ), 10, 2 );
	}

	/**
	 * @param object $document A WPO\IPS\Documents\OrderDocument.
	 * @param mixed  $order    The order it belongs to.
	 */
	public function saved( $document, $order ): void {
		if ( ! $order instanceof WC_Order || ! method_exists( $document, 'get_type' ) || 'invoice' !== $document->get_type() ) {
			return;
		}
		self::guarded(
			function () use ( $order ): void {
				if ( '' !== (string) $order->get_meta( Meta::RECORD_ID ) ) {
					$this->check_unchanged( $order );
					return;
				}
				$this->queue( $order );
			}
		);
	}

	/**
	 * @param mixed $type  Document type.
	 * @param mixed $order The order.
	 */
	public function rendering( $type, $order ): void {
		if ( 'invoice' === $type && $order instanceof WC_Order ) {
			self::guarded(
				function () use ( $order ): void {
					$this->queue( $order );
				}
			);
		}
	}

	/**
	 * Queue a first send. Only for a new or pending invoice: a retrying order already has its
	 * next try scheduled, and an error or unsupported one waits for a person ("Reintentar").
	 */
	private function queue( WC_Order $order ): void {
		$status = (string) $order->get_meta( Meta::STATUS );
		if ( ( '' !== $status && Status::PENDING !== $status ) || null === OrderReader::invoice( $order ) ) {
			return;
		}
		if ( '' === $status ) {
			$order->update_meta_data( Meta::STATUS, Status::PENDING );
			$order->save_meta_data();
		}
		$this->scheduler->send_soon( $order->get_id() );
	}

	/**
	 * These hooks may run inside checkout or a customer's PDF download: nothing we do may
	 * break that request. A failure is logged; the render-time safety net tries again.
	 */
	private static function guarded( callable $work ): void {
		try {
			$work();
		} catch ( \Throwable $error ) {
			wc_get_logger()->error( 'Huella: could not queue the invoice registration: ' . $error->getMessage(), array( 'source' => 'huella-verifactu' ) );
		}
	}

	private function check_unchanged( WC_Order $order ): void {
		$invoice = OrderReader::invoice( $order );
		$number  = null === $invoice ? null : $invoice->get_number();
		$date    = null === $invoice ? null : $invoice->get_date();
		$current = array(
			is_object( $number ) ? $number->get_formatted() : '',
			$date instanceof \WC_DateTime ? $date->date( 'Y-m-d' ) : '',
		);
		$registered = array( (string) $order->get_meta( Meta::INVOICE_NUMBER ), (string) $order->get_meta( Meta::INVOICE_DATE ) );
		if ( $current === $registered || '' !== (string) $order->get_meta( Meta::CHANGED ) ) {
			return;
		}
		$order->update_meta_data( Meta::CHANGED, implode( ' ', $current ) );
		$order->save_meta_data();
		$order->add_order_note(
			sprintf(
				/* translators: 1: registered number, 2: registered date, 3: new number, 4: new date */
				__( 'Verifactu: la factura se registró como %1$s del %2$s y ahora es %3$s del %4$s. El registro en la AEAT no cambia: anula el registro y emite una factura nueva.', 'huella-verifactu' ),
				$registered[0],
				$registered[1],
				'' === $current[0] ? '—' : $current[0],
				'' === $current[1] ? '—' : $current[1]
			)
		);
	}
}
