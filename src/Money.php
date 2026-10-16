<?php
/**
 * Amounts as integer cents, written the one way the AEAT accepts (SPEC 3.3, CLAUDE.md "money").
 *
 * Why not round() on floats: its result for halves such as 0.285 changed in PHP 8.4, and a shop
 * may run anything from 7.4 to 8.4. Rounding here works on the decimal digits instead, half away
 * from zero, exactly like huella's round_amount(). Sums are then exact integer additions.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu;

use InvalidArgumentException;

final class Money {

	private const MAX_INTEGER_DIGITS = 12; // AEAT amount pattern: -?\d{1,12}\.\d{2}
	private const MICROS_PER_CENT    = 10000;

	/**
	 * Cents of an amount, rounded half away from zero: "0.285" -> 29, "-0.285" -> -29.
	 *
	 * @param string|int|float $value A decimal string as WooCommerce stores it, or a number.
	 */
	public static function to_cents( $value ): int {
		return self::units( $value, 2 );
	}

	/**
	 * Millionths of the currency unit (WooCommerce keeps line totals to 6 decimals). Sums of
	 * many lines are done in micros, so they are exact before the one rounding to cents.
	 *
	 * @param string|int|float $value Any amount.
	 */
	public static function to_micros( $value ): int {
		return self::units( $value, 6 );
	}

	/**
	 * Micros to cents, half away from zero: 285000 -> 29, -285000 -> -29.
	 */
	public static function micros_to_cents( int $micros ): int {
		$cents = intdiv( abs( $micros ) + self::MICROS_PER_CENT / 2, self::MICROS_PER_CENT );
		return $micros < 0 ? -$cents : $cents;
	}

	/**
	 * The amount in units of 10^-decimals, rounded half away from zero on its decimal digits.
	 *
	 * @param string|int|float $value Any amount.
	 */
	private static function units( $value, int $decimals ): int {
		$text = is_string( $value ) ? trim( $value ) : self::number_to_text( $value );
		if ( ! preg_match( '/^(-?)(\d+)(?:\.(\d+))?$/', $text, $parts ) ) {
			throw new InvalidArgumentException( "Not a decimal amount: '{$text}'" );
		}
		$negative = '-' === $parts[1];
		$digits   = str_pad( $parts[3] ?? '', $decimals + 1, '0' );
		$units    = (int) $parts[2] * 10 ** $decimals + (int) substr( $digits, 0, $decimals );
		if ( $digits[ $decimals ] >= '5' ) { // the rest is at least half a unit: away from zero.
			++$units;
		}
		return $negative ? -$units : $units;
	}

	/**
	 * The AEAT text of an amount in cents: 1736 -> "17.36", -5 -> "-0.05".
	 */
	public static function format( int $cents ): string {
		$sign     = $cents < 0 ? '-' : '';
		$absolute = abs( $cents );
		$integer  = intdiv( $absolute, 100 );
		if ( strlen( (string) $integer ) > self::MAX_INTEGER_DIGITS ) {
			throw new InvalidArgumentException( 'Amount too large for the AEAT: ' . $cents . ' cents' );
		}
		return sprintf( '%s%d.%02d', $sign, $integer, $absolute % 100 );
	}

	/**
	 * Round, then format: the text that is hashed and sent (CLAUDE.md "round, then format").
	 *
	 * @param string|int|float $value Any amount.
	 */
	public static function text( $value ): string {
		return self::format( self::to_cents( $value ) );
	}

	/**
	 * A float or int as a plain decimal string, without exponent and with 6 decimals: far beyond
	 * the cent, and close enough to the float that 0.285 stays "0.285000".
	 *
	 * @param int|float $value A number.
	 */
	private static function number_to_text( $value ): string {
		if ( is_float( $value ) && ! is_finite( $value ) ) {
			throw new InvalidArgumentException( 'Amounts must be finite numbers' );
		}
		return number_format( (float) $value, 6, '.', '' );
	}
}
