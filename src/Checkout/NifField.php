<?php
/**
 * Optional "NIF/CIF" field at checkout (SPEC 6.4, 6.5): with a valid NIF, or an EU VAT number
 * of the billing country, the invoice is a full one (F1) instead of a simplified one (F2).
 *
 * - Classic checkout: a billing field; WooCommerce stores it as _billing_huella_nif.
 * - Block checkout (WooCommerce 8.9+): an additional field in the "order" location; stored as
 *   _wc_other/huella-verifactu/nif (CheckoutFields::OTHER_FIELDS_PREFIX, checked in WC 11.2).
 *
 * Shops that already collect the NIF with another plugin choose "read from meta key" instead.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Checkout;

use Huella\Verifactu\Nif;
use WC_Order;
use WP_Error;

final class NifField {

	public const CLASSIC_KEY = 'billing_huella_nif';
	public const CLASSIC_META = '_billing_huella_nif';
	public const BLOCK_ID     = 'huella-verifactu/nif';
	public const BLOCK_META   = '_wc_other/huella-verifactu/nif';

	public static function register(): void {
		add_filter( 'woocommerce_billing_fields', array( self::class, 'classic_field' ) );
		add_action( 'woocommerce_after_checkout_validation', array( self::class, 'classic_validation' ), 10, 2 );
		add_action( 'woocommerce_init', array( self::class, 'block_field' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $fields Billing fields.
	 * @return array<string, array<string, mixed>>
	 */
	public static function classic_field( array $fields ): array {
		$fields[ self::CLASSIC_KEY ] = array(
			'label'       => __( 'NIF/CIF (opcional, para factura completa)', 'huella-verifactu' ),
			'required'    => false,
			'class'       => array( 'form-row-wide' ),
			'priority'    => 35, // Right after the company name.
			'maxlength'   => 20,
			'autocomplete' => 'off',
		);
		return $fields;
	}

	/**
	 * @param array<string, mixed> $data   Posted checkout data.
	 * @param WP_Error             $errors Checkout errors.
	 */
	public static function classic_validation( array $data, WP_Error $errors ): void {
		$value   = is_string( $data[ self::CLASSIC_KEY ] ?? null ) ? $data[ self::CLASSIC_KEY ] : '';
		$country = is_string( $data['billing_country'] ?? null ) ? $data['billing_country'] : '';
		$message = self::problem( $value, $country );
		if ( null !== $message ) {
			$errors->add( 'validation', $message );
		}
	}

	public static function block_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return; // WooCommerce before 8.9: the classic field still works.
		}
		woocommerce_register_additional_checkout_field(
			array(
				'id'                => self::BLOCK_ID,
				'label'             => __( 'NIF/CIF (opcional, para factura completa)', 'huella-verifactu' ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => false,
				'validate_callback' => static function ( $value ) {
					$message = self::problem( is_string( $value ) ? $value : '', '' );
					return null === $message ? null : new WP_Error( 'huella_invalid_nif', $message );
				},
			)
		);
	}

	/**
	 * The value typed at checkout, from whichever checkout was used ("" when none).
	 */
	public static function value( WC_Order $order ): string {
		foreach ( array( self::CLASSIC_META, self::BLOCK_META ) as $key ) {
			$value = $order->get_meta( $key );
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}
		return '';
	}

	/**
	 * Why a typed tax ID cannot be used, or null if it can (or is empty: the field is optional).
	 * The billing country is unknown in the block callback, so any EU-looking VAT number passes
	 * there; the mapper then decides with the real country.
	 */
	public static function problem( string $value, string $country ): ?string {
		$value = strtoupper( (string) preg_replace( '/[\s.\-]/', '', $value ) );
		if ( '' === $value || Nif::is_valid( $value ) ) {
			return null;
		}
		$eu_vat = 1 === preg_match( '/^[A-Z]{2}[0-9A-Z+*]{2,12}$/', $value ) && 0 !== strpos( $value, 'ES' );
		if ( $eu_vat && ( '' === $country || 'ES' !== $country ) ) {
			return null;
		}
		return __( 'El NIF/CIF no es válido. Revísalo o déjalo vacío para recibir una factura simplificada.', 'huella-verifactu' );
	}
}
