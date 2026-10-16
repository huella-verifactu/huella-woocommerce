<?php
/**
 * The plugin's settings (SPEC 6.4), stored in one option. The settings page writes them.
 *
 * The API key may instead be a constant in wp-config.php (recommended, SPEC 6.4):
 *     define( 'HUELLA_API_KEY', 'hk_live_...' );
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Woo;

use Huella\Verifactu\Api\ApiClient;
use Huella\Verifactu\Api\WpHttp;
use Huella\Verifactu\Checkout\NifField;
use Huella\Verifactu\Invoice\MapperSettings;
use InvalidArgumentException;
use WC_Order;

final class Options {

	public const OPTION = 'huella_verifactu_settings';
	// Placeholder until the service domain is bought (PLAN 2, Fri 16 Oct); overridable with the
	// HUELLA_API_URL constant, which is also how staging and the tests point elsewhere.
	public const DEFAULT_API_URL         = 'https://api.huella.es';
	public const DEFAULT_DESCRIPTION     = 'Venta online - pedido {order_number}';
	public const DEFAULT_TAX_ID_META_KEY = '_billing_huella_nif';

	/**
	 * @return array<string, string>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		$values = array();
		foreach ( is_array( $stored ) ? $stored : array() as $key => $value ) {
			if ( is_string( $key ) && is_scalar( $value ) ) {
				$values[ $key ] = (string) $value;
			}
		}
		return $values;
	}

	public static function get( string $key, string $fallback = '' ): string {
		$value = self::all()[ $key ] ?? '';
		return '' === $value ? $fallback : $value;
	}

	public static function api_key(): string {
		return defined( 'HUELLA_API_KEY' ) ? (string) constant( 'HUELLA_API_KEY' ) : self::get( 'api_key' );
	}

	public static function api_url(): string {
		return defined( 'HUELLA_API_URL' ) ? (string) constant( 'HUELLA_API_URL' ) : self::DEFAULT_API_URL;
	}

	/**
	 * The client, or null while no valid key is set.
	 */
	public static function api(): ?ApiClient {
		if ( 0 !== strpos( self::api_url(), 'https://' ) && 'local' !== wp_get_environment_type() ) {
			return null; // An http:// URL would send the API key in clear text.
		}
		try {
			return new ApiClient( new WpHttp(), self::api_key(), self::api_url() );
		} catch ( InvalidArgumentException $not_a_key ) {
			return null;
		}
	}

	/**
	 * Mapper settings, or null while no issuer is chosen.
	 */
	public static function mapper_settings(): ?MapperSettings {
		$issuer = self::get( 'issuer_id' );
		if ( '' === $issuer ) {
			return null;
		}
		return new MapperSettings(
			$issuer,
			self::get( 'simplified_limit', '400.00' ),
			self::get( 'description_template', self::DEFAULT_DESCRIPTION ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
		);
	}

	/**
	 * Whether the plugin adds its own NIF field at checkout (default) or reads an existing one.
	 */
	public static function adds_nif_field(): bool {
		return 'meta' !== self::get( 'nif_mode', 'field' );
	}

	/**
	 * The customer's NIF or EU VAT number as stored on the order ("" when none).
	 */
	public static function tax_id( WC_Order $order ): string {
		if ( self::adds_nif_field() ) {
			return NifField::value( $order );
		}
		$value = $order->get_meta( self::get( 'tax_id_meta_key', self::DEFAULT_TAX_ID_META_KEY ) );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Keep the chosen issuer's simplified-invoice limit next to its ID (SPEC 6.4, read-only).
	 *
	 * @param list<array{id: string, nif: string, legal_name: string, simplified_limit: string}> $issuers From the API.
	 */
	public static function remember_issuer_limit( array $issuers ): void {
		$values = self::all();
		foreach ( $issuers as $issuer ) {
			if ( $issuer['id'] === ( $values['issuer_id'] ?? '' ) ) {
				$values['simplified_limit'] = $issuer['simplified_limit'];
				update_option( self::OPTION, $values );
				return;
			}
		}
	}
}
