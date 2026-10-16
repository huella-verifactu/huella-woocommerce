<?php
/**
 * The rules the AEAT applies to NumSerieFactura: a port of huella's check_invoice_number().
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu;

use InvalidArgumentException;

final class InvoiceNumber {

	private const MAX_LENGTH = 60;
	private const FORBIDDEN  = array( '"', "'", '<', '>', '=' );

	/**
	 * The number itself if the AEAT accepts it; otherwise an exception that says why.
	 *
	 * @throws InvalidArgumentException When the number breaks a rule.
	 */
	public static function check( string $number ): string {
		$length = strlen( $number );
		if ( $length < 1 || $length > self::MAX_LENGTH ) {
			throw new InvalidArgumentException( 'An invoice number has 1 to 60 characters' );
		}
		if ( trim( $number, ' ' ) !== $number ) {
			throw new InvalidArgumentException( 'An invoice number must not start or end with spaces' );
		}
		if ( preg_match( '/[^\x20-\x7E]/', $number ) ) {
			throw new InvalidArgumentException( 'An invoice number may use only printable ASCII characters' );
		}
		foreach ( self::FORBIDDEN as $character ) {
			if ( false !== strpos( $number, $character ) ) {
				throw new InvalidArgumentException( "The AEAT rejects the character {$character} in invoice numbers" );
			}
		}
		return $number;
	}
}
