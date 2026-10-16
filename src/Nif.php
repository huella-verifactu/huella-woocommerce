<?php
/**
 * Spanish tax ID checksums: a port of huella's nif.py, tested on the same fixture file.
 *
 * All three kinds are 9 characters; the last is a control character computed from the others:
 * - DNI "12345678Z": 8 digits, letter = TRWAGMYFPDXBNJZSQVHLCKE[number % 23].
 * - NIE "X1234567L": X/Y/Z stand for 0/1/2, then the DNI rule on the 8 digits.
 * - K/L/M "M1234567L": individuals without DNI/NIE; the DNI rule on the 7 digits.
 * - CIF "B12345674": organisation letter, 7 digits, then a Luhn-style digit or its letter
 *   (JABCDEFGHI). Both forms are accepted for every organisation letter, as core does.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu;

final class Nif {

	private const DNI_LETTERS        = 'TRWAGMYFPDXBNJZSQVHLCKE';
	private const NIE_PREFIX         = array(
		'X' => '0',
		'Y' => '1',
		'Z' => '2',
	);
	private const CIF_ORGANISATIONS  = 'ABCDEFGHJNPQRSUVW';
	private const CIF_CONTROL_LETTERS = 'JABCDEFGHI';

	/**
	 * Uppercase and drop spaces, hyphens and dots: " b-1234567.4" -> "B12345674".
	 */
	public static function normalize( string $value ): string {
		return strtoupper( str_replace( array( ' ', '-', '.' ), '', $value ) );
	}

	/**
	 * True if the value (after normalize()) is a checksum-valid NIF, NIE or CIF.
	 */
	public static function is_valid( string $value ): bool {
		$nif = self::normalize( $value );
		if ( ! preg_match( '/^[0-9A-Z]\d{7}[0-9A-Z]$/', $nif ) ) {
			return false;
		}
		$first   = $nif[0];
		$digits  = substr( $nif, 1, 7 );
		$control = $nif[8];
		if ( ctype_digit( $first ) ) {
			return self::dni_letter( (int) substr( $nif, 0, 8 ) ) === $control;
		}
		if ( isset( self::NIE_PREFIX[ $first ] ) ) {
			return self::dni_letter( (int) ( self::NIE_PREFIX[ $first ] . $digits ) ) === $control;
		}
		if ( false !== strpos( 'KLM', $first ) ) {
			return self::dni_letter( (int) $digits ) === $control;
		}
		if ( false !== strpos( self::CIF_ORGANISATIONS, $first ) ) {
			return false !== strpos( self::cif_check_characters( $digits ), $control );
		}
		return false;
	}

	private static function dni_letter( int $number ): string {
		return self::DNI_LETTERS[ $number % 23 ];
	}

	/**
	 * The two accepted CIF controls for 7 digits: the digit and its letter ("1234567" -> "4D").
	 */
	private static function cif_check_characters( string $digits ): string {
		$total = 0;
		foreach ( str_split( $digits ) as $position => $char ) {
			$value = (int) $char;
			if ( 0 === $position % 2 ) { // 1st, 3rd, 5th and 7th digits are doubled, then digit-summed.
				$value = intdiv( $value * 2, 10 ) + ( $value * 2 ) % 10;
			}
			$total += $value;
		}
		$check = ( 10 - $total % 10 ) % 10;
		return $check . self::CIF_CONTROL_LETTERS[ $check ];
	}
}
