<?php
/**
 * The Huella-Signature header of a webhook (SPEC 5.8, 6.9). Pure: tested without WordPress.
 *
 * Header: "t=<unix>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")>". An event older or newer
 * than 5 minutes is refused, so a captured request cannot be replayed later.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Webhook;

final class Signature {

	public const HEADER    = 'Huella-Signature';
	public const TOLERANCE = 300; // Seconds.

	public static function verify( string $header, string $body, string $secret, int $now ): bool {
		if ( '' === $secret ) {
			return false;
		}
		$timestamp  = null;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] && ctype_digit( $pair[1] ) ) {
				$timestamp = $pair[1]; // The exact text that was signed.
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}
		if ( null === $timestamp || abs( $now - (int) $timestamp ) > self::TOLERANCE ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}
}
