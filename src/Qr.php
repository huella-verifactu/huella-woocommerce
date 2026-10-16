<?php
/**
 * The AEAT QR validation URL (SPEC 3.7, 6.7): byte-identical to huella's qr.url_from_fields().
 *
 * It depends only on NIF, number, date and total, never on the huella, so the invoice PDF can
 * carry it before the record reaches the AEAT (D7). tests/fixtures/qr_cases.json is shared with
 * core and checked against it in CI.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu;

use InvalidArgumentException;

final class Qr {

	public const HOSTS = array(
		'test' => 'https://prewww2.aeat.es',
		'live' => 'https://www2.agenciatributaria.gob.es',
	);
	private const PATH = '/wlpl/TIKE-CONT/ValidarQR';

	/**
	 * Validation URL. Values are percent-encoded per RFC 3986 (rawurlencode), the only place
	 * where encoding happens: the hash and the XML use the raw values.
	 *
	 * @param string $nif         Issuer NIF.
	 * @param string $number      Invoice number (NumSerieFactura).
	 * @param string $issue_date  Issue date as YYYY-MM-DD.
	 * @param string $total       Total as the AEAT text, e.g. "100.00" (see Money::text()).
	 * @param string $environment "test" (pre-production) or "live".
	 * @throws InvalidArgumentException When a value is not valid for the AEAT.
	 */
	public static function url( string $nif, string $number, string $issue_date, string $total, string $environment ): string {
		if ( ! isset( self::HOSTS[ $environment ] ) ) {
			throw new InvalidArgumentException( "Unknown environment '{$environment}'" );
		}
		$normalized = Nif::normalize( $nif );
		if ( ! Nif::is_valid( $normalized ) ) {
			throw new InvalidArgumentException( "Not a valid Spanish NIF: '{$nif}'" );
		}
		if ( ! preg_match( '/^-?\d{1,12}\.\d{2}$/', $total ) ) {
			throw new InvalidArgumentException( "The total must have exactly 2 decimals: '{$total}'" );
		}
		$params = array(
			'nif'      => $normalized,
			'numserie' => InvoiceNumber::check( $number ),
			'fecha'    => self::aeat_date( $issue_date ),
			'importe'  => $total,
		);
		$query = array();
		foreach ( $params as $name => $value ) {
			$query[] = $name . '=' . rawurlencode( $value );
		}
		return self::HOSTS[ $environment ] . self::PATH . '?' . implode( '&', $query );
	}

	/**
	 * YYYY-MM-DD -> DD-MM-YYYY, rejecting dates that do not exist.
	 */
	private static function aeat_date( string $iso ): string {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			throw new InvalidArgumentException( "Not a date (YYYY-MM-DD): '{$iso}'" );
		}
		return "{$parts[3]}-{$parts[2]}-{$parts[1]}";
	}
}
