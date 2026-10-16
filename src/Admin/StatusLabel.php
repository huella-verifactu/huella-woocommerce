<?php
/**
 * How each state looks in the orders list and the meta box (SPEC 6.10).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Admin;

use Huella\Verifactu\Sending\Status;

final class StatusLabel {

	private const COLOURS = array(
		'grey'  => array( '#f0f0f1', '#50575e' ),
		'blue'  => array( '#dbe9f7', '#135e96' ),
		'green' => array( '#d7f0db', '#00702a' ),
		'amber' => array( '#fcf0d0', '#7a5600' ),
		'red'   => array( '#facfd2', '#8a2424' ),
	);

	/**
	 * @return array{string, string} Label and colour name.
	 */
	public static function for_status( string $status ): array {
		switch ( $status ) {
			case Status::PENDING:
				return array( __( 'Pendiente', 'huella-verifactu' ), 'grey' );
			case 'queued':
			case 'sending':
				return array( __( 'Enviado', 'huella-verifactu' ), 'blue' );
			case 'accepted':
				return array( __( 'Aceptado', 'huella-verifactu' ), 'green' );
			case 'accepted_with_errors':
				return array( __( 'Aceptado con errores', 'huella-verifactu' ), 'amber' );
			case 'rejected':
				return array( __( 'Rechazado', 'huella-verifactu' ), 'red' );
			case Status::RETRYING:
				return array( __( 'Reintentando', 'huella-verifactu' ), 'amber' );
			case Status::ERROR:
				return array( __( 'Error', 'huella-verifactu' ), 'red' );
			case Status::UNSUPPORTED:
				return array( __( 'No registrable', 'huella-verifactu' ), 'red' );
			case Status::NOT_CONFIGURED:
				return array( __( 'Sin configurar', 'huella-verifactu' ), 'grey' );
			default:
				return array( '', 'grey' );
		}
	}

	/**
	 * The badge HTML ("–" for an order without an invoice), escaped.
	 */
	public static function badge( string $status ): string {
		list( $label, $colour ) = self::for_status( $status );
		if ( '' === $label ) {
			return '<span aria-hidden="true">–</span>';
		}
		list( $background, $text ) = self::COLOURS[ $colour ];
		return sprintf(
			'<mark class="huella-verifactu-status" style="background:%1$s;color:%2$s;padding:2px 8px;border-radius:3px;white-space:nowrap;">%3$s</mark>',
			esc_attr( $background ),
			esc_attr( $text ),
			esc_html( $label )
		);
	}

	/**
	 * States from which "Reintentar envío" makes sense: nothing is registered yet.
	 */
	public static function can_retry( string $status ): bool {
		return in_array( $status, array( Status::ERROR, Status::UNSUPPORTED, Status::NOT_CONFIGURED, Status::RETRYING ), true );
	}
}
