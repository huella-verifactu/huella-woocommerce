<?php
/**
 * The webhook endpoint this shop registered with Huella Cloud (SPEC 6.9), as stored in an
 * option: its ID, the signing secret, the URL and which API key registered it. Pure: tested
 * without WordPress.
 *
 * Registering the same URL again gives a new secret (SPEC 5.8), so the plugin registers only
 * when nothing valid is stored, the site's URL changed, or the API key changed.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Webhook;

final class Registration {

	/**
	 * A fingerprint of the API key: notices a new key without storing the key a second time.
	 */
	public static function key_hash( string $api_key ): string {
		return hash( 'sha256', $api_key );
	}

	/**
	 * The stored registration, or null when there is none or it is malformed.
	 *
	 * @param mixed $stored The option value.
	 * @return array{id: string, secret: string, url: string, key: string}|null
	 */
	public static function read( $stored ): ?array {
		if ( ! is_array( $stored ) ) {
			return null;
		}
		foreach ( array( 'id', 'secret', 'url', 'key' ) as $field ) {
			if ( ! is_string( $stored[ $field ] ?? null ) || '' === $stored[ $field ] ) {
				return null;
			}
		}
		return array(
			'id'     => $stored['id'],
			'secret' => $stored['secret'],
			'url'    => $stored['url'],
			'key'    => $stored['key'],
		);
	}

	/**
	 * @param mixed $stored The option value.
	 */
	public static function is_current( $stored, string $url, string $key_hash ): bool {
		$registration = self::read( $stored );
		return null !== $registration && $registration['url'] === $url && hash_equals( $registration['key'], $key_hash );
	}

	/**
	 * What to store from the answer to POST /v1/webhook-endpoints, or null if it is not valid.
	 *
	 * @param array<string, mixed> $answer The endpoint as Huella answered.
	 * @return array{id: string, secret: string, url: string, key: string}|null
	 */
	public static function from_answer( array $answer, string $url, string $key_hash ): ?array {
		$id     = $answer['id'] ?? null;
		$secret = $answer['secret'] ?? null;
		if ( ! is_string( $id ) || ! preg_match( '/^whe_[0-9a-f]{32}$/', $id ) ) {
			return null;
		}
		if ( ! is_string( $secret ) || ! preg_match( '/^whsec_[A-Za-z0-9_-]{32,}$/', $secret ) ) {
			return null;
		}
		return array(
			'id'     => $id,
			'secret' => $secret,
			'url'    => $url,
			'key'    => $key_hash,
		);
	}
}
