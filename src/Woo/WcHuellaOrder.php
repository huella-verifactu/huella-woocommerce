<?php
/**
 * HuellaOrder on a WC_Order. Only WC_Order methods touch the data (HPOS-safe, SPEC 6.1).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Woo;

use Huella\Verifactu\Invoice\OrderSnapshot;
use Huella\Verifactu\Sending\HuellaOrder;
use Huella\Verifactu\Sending\Status;
use WC_Order;

final class WcHuellaOrder implements HuellaOrder {

	/** @var WC_Order */
	private $order;
	/** @var string */
	private $tax_id;

	public function __construct( WC_Order $order, string $tax_id ) {
		$this->order  = $order;
		$this->tax_id = $tax_id;
	}

	public function id(): int {
		return $this->order->get_id();
	}

	public function snapshot(): ?OrderSnapshot {
		return OrderReader::read( $this->order, $this->tax_id );
	}

	public function record_id(): ?string {
		$id = $this->order->get_meta( Meta::RECORD_ID );
		return is_string( $id ) && '' !== $id ? $id : null;
	}

	public function failures(): int {
		return (int) $this->order->get_meta( Meta::FAILURES );
	}

	public function registered( array $record, OrderSnapshot $snapshot, array $notes ): void {
		$text = static function ( string $key ) use ( $record ): string {
			return is_string( $record[ $key ] ?? null ) ? $record[ $key ] : '';
		};
		$this->order->update_meta_data( Meta::RECORD_ID, $text( 'id' ) );
		$this->order->update_meta_data( Meta::STATUS, $text( 'status' ) );
		$this->order->update_meta_data( Meta::HUELLA, $text( 'huella' ) );
		$this->order->update_meta_data( Meta::ENVIRONMENT, $text( 'environment' ) );
		$this->order->update_meta_data( Meta::QR_URL, $text( 'qr_url' ) );
		$this->order->update_meta_data( Meta::INVOICE_NUMBER, $snapshot->invoice_number );
		$this->order->update_meta_data( Meta::INVOICE_DATE, $snapshot->invoice_date );
		$this->order->delete_meta_data( Meta::FAILURES );
		$this->order->delete_meta_data( Meta::LAST_ERROR );
		$this->order->save();

		$environment = 'live' === $text( 'environment' ) ? __( 'producción', 'huella-verifactu' ) : __( 'pruebas', 'huella-verifactu' );
		$this->order->add_order_note(
			sprintf(
				/* translators: 1: invoice number, 2: environment, 3: first characters of the huella */
				__( 'Verifactu: factura %1$s registrada en Huella (%2$s). Huella %3$s…', 'huella-verifactu' ),
				$snapshot->invoice_number,
				$environment,
				substr( $text( 'huella' ), 0, 12 )
			)
		);
		foreach ( $notes as $note ) {
			$this->order->add_order_note( 'Verifactu: ' . $note );
		}
	}

	public function not_registered( string $status, string $code, string $message, int $failures ): void {
		$previous = $this->order->get_meta( Meta::STATUS );
		$this->order->update_meta_data( Meta::STATUS, $status );
		$this->order->update_meta_data( Meta::LAST_ERROR, "{$code}: {$message}" );
		$this->order->update_meta_data( Meta::FAILURES, (string) $failures );
		$this->order->save();
		// One note when the state changes, not one per hourly retry.
		if ( $previous !== $status ) {
			$this->order->add_order_note( self::note( $status, $message ) );
		}
	}

	private static function note( string $status, string $message ): string {
		switch ( $status ) {
			case Status::RETRYING:
				/* translators: %s: error */
				return sprintf( __( 'Verifactu: Huella no responde (%s). Se reintentará automáticamente.', 'huella-verifactu' ), $message );
			case Status::UNSUPPORTED:
				/* translators: %s: reason */
				return sprintf( __( 'Verifactu: este pedido no se puede registrar automáticamente (%s). No se ha enviado nada.', 'huella-verifactu' ), $message );
			case Status::NOT_CONFIGURED:
				return __( 'Verifactu: falta configurar la clave de API y el emisor (WooCommerce → Ajustes → Verifactu).', 'huella-verifactu' );
			default:
				/* translators: %s: error */
				return sprintf( __( 'Verifactu: Huella ha rechazado el envío (%s). Corrige el problema y pulsa «Reintentar envío».', 'huella-verifactu' ), $message );
		}
	}
}
