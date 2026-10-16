<?php
/**
 * The order note for a record state reported by Huella Cloud (SPEC 3.8, 6.9). Pure: the
 * texts are tested without WordPress.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Sending;

final class StatusNote {

	/**
	 * Note for the new state, or null when nothing worth telling the shop happened.
	 *
	 * @param array<string, mixed> $aeat The record's "aeat" block: status, code, message.
	 */
	public static function for_state( string $status, array $aeat ): ?string {
		$detail = self::detail( $aeat );
		switch ( $status ) {
			case 'accepted':
				return __( 'Verifactu: la AEAT ha aceptado el registro.', 'huella-verifactu' );
			case 'accepted_with_errors':
				/* translators: %s: AEAT code and message */
				return sprintf( __( 'Verifactu: la AEAT ha aceptado el registro con errores (%s). Revisa la factura: puede necesitar una subsanación.', 'huella-verifactu' ), $detail );
			case 'rejected':
				/* translators: %s: AEAT code and message */
				return sprintf( __( 'Verifactu: la AEAT ha rechazado el registro (%s). Hay que corregirlo y volver a enviarlo.', 'huella-verifactu' ), $detail );
			default:
				return null; // queued, sending: still on its way.
		}
	}

	/**
	 * @param array<string, mixed> $aeat The record's "aeat" block.
	 */
	private static function detail( array $aeat ): string {
		$code    = is_scalar( $aeat['code'] ?? null ) ? (string) $aeat['code'] : '';
		$message = is_string( $aeat['message'] ?? null ) ? $aeat['message'] : '';
		$text    = trim( ( '' === $code ? '' : __( 'código', 'huella-verifactu' ) . " {$code}: " ) . $message, ' :' );
		$text    = '' === $text ? __( 'sin detalle', 'huella-verifactu' ) : $text;
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 300 ) : substr( $text, 0, 300 );
	}
}
