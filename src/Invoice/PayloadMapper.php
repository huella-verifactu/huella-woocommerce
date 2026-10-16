<?php
/**
 * Order with a WP Overnight invoice -> the body of POST /v1/invoices (SPEC 6.5).
 *
 * Steps, each a method below:
 * 1. Invoice type: a valid Spanish NIF, or an EU VAT number from another EU country, makes a
 *    full invoice (F1) with that recipient; anything else a simplified one (F2).
 * 2. Tax lines: every product, shipping and fee line goes into the group of its one tax rate
 *    (21, 10 or 4 %). Sums are exact (micros), rounded to cents once per group.
 * 3. Reconciliation: base + tax of all groups must equal what the customer paid. A difference
 *    of up to 2 cents (rounding) goes into the base of the largest group, with a note;
 *    anything bigger is not sent.
 * 4. Limits: a simplified invoice may not exceed the issuer's limit (400.00 by default).
 *
 * The tax amounts are WooCommerce's, never recomputed: they are what the invoice PDF shows.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Invoice;

use Huella\Verifactu\InvoiceNumber;
use Huella\Verifactu\Money;
use Huella\Verifactu\Nif;
use InvalidArgumentException;

final class PayloadMapper {

	public const SUPPORTED_RATES      = array( '21.00', '10.00', '4.00' ); // SPEC 1.1: IVA only.
	public const ROUNDING_TOLERANCE   = 2; // Cents (SPEC 6.5 step 5).
	private const NAME_MAX            = 120;
	private const DESCRIPTION_MAX     = 500;
	private const EU_COUNTRIES        = array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK' );
	private const VAT_PREFIX          = array( 'GR' => 'EL' ); // Greece's VAT numbers start with EL.
	private const ID_TYPE_VAT         = '02'; // IDOtro type: NIF-IVA (EU VAT number).

	/**
	 * The payload, or Unsupported with the reason when the order must not be sent.
	 *
	 * @throws Unsupported When the order cannot be registered as it is.
	 */
	public function map( OrderSnapshot $order, MapperSettings $settings ): Mapped {
		$notes     = array();
		$recipient = $this->recipient( $order->customer, $notes );
		$groups    = $this->tax_groups( $order->lines );
		$total     = Money::to_cents( $order->total );
		$groups    = $this->reconcile( $groups, $total, $notes );
		$type      = null === $recipient ? 'F2' : 'F1';

		if ( 'F2' === $type && $total > Money::to_cents( $settings->simplified_limit ) ) {
			throw new Unsupported(
				'simplified_limit_exceeded',
				"A simplified invoice may not exceed {$settings->simplified_limit}; this order needs a full invoice with the customer's NIF"
			);
		}

		$payload = array(
			'issuer_id'     => $settings->issuer_id,
			'series_number' => $this->invoice_number( $order->invoice_number ),
			'issue_date'    => $order->invoice_date,
			'type'          => $type,
			'description'   => $this->description( $order, $settings ),
			'tax_lines'     => $this->tax_lines( $groups ),
			'total'         => Money::format( $total ),
			'external_ref'  => 'wc_order_' . $order->order_id,
		);
		if ( null !== $recipient ) {
			$payload['recipient'] = $recipient;
		}
		return new Mapped( $payload, $notes );
	}

	/**
	 * Step 1. The recipient of a full invoice, or null for a simplified one.
	 *
	 * @param list<string> $notes Collects what the admin should know.
	 * @return array<string, string>|null
	 */
	private function recipient( Customer $customer, array &$notes ): ?array {
		$tax_id = strtoupper( trim( $customer->tax_id ) );
		if ( '' === $tax_id ) {
			return null;
		}
		$name = $this->truncate( trim( $customer->name ), self::NAME_MAX );
		if ( Nif::is_valid( $tax_id ) ) {
			return array(
				'name' => $name,
				'nif'  => Nif::normalize( $tax_id ),
			);
		}
		$vat = preg_replace( '/[\s.\-]/', '', $tax_id ) ?? '';
		if ( $this->is_eu_vat( $vat, $customer->country ) ) {
			return array(
				'name'    => $name,
				'country' => $customer->country,
				'id_type' => self::ID_TYPE_VAT,
				'id'      => $vat,
			);
		}
		$notes[] = "The tax ID '{$customer->tax_id}' is not a valid Spanish NIF nor an EU VAT number of the billing country: registered as a simplified invoice (F2)";
		return null;
	}

	private function is_eu_vat( string $vat, string $country ): bool {
		if ( 'ES' === $country || ! in_array( $country, self::EU_COUNTRIES, true ) ) {
			return false;
		}
		$prefix = self::VAT_PREFIX[ $country ] ?? $country;
		return 1 === preg_match( '/^' . $prefix . '[0-9A-Z+*]{2,12}$/', $vat );
	}

	/**
	 * Step 2. Exact sums per tax rate, in micros: rate => [base, tax].
	 *
	 * @param list<OrderLine> $lines Products, shipping and fees.
	 * @return array<string, array{int, int}>
	 * @throws Unsupported On a line this version cannot register (SPEC 1.2).
	 */
	private function tax_groups( array $lines ): array {
		$groups = array();
		foreach ( $lines as $line ) {
			$net   = Money::to_micros( $line->net );
			$taxes = array();
			foreach ( $line->taxes as $percent => $amount ) {
				$micros = Money::to_micros( $amount );
				if ( 0 !== $micros ) {
					$taxes[ Money::text( (string) $percent ) ] = $micros;
				}
			}
			if ( 0 === $net && ! $taxes ) {
				continue; // Free shipping, a zero fee: nothing to declare.
			}
			if ( ! $taxes ) {
				throw new Unsupported( 'untaxed_line', "'{$line->label}' has no tax: exempt or 0 % lines are not supported yet" );
			}
			if ( count( $taxes ) > 1 ) {
				throw new Unsupported( 'multiple_tax_rates', "'{$line->label}' has more than one tax rate" );
			}
			$rate = (string) array_key_first( $taxes );
			if ( ! in_array( $rate, self::SUPPORTED_RATES, true ) ) {
				throw new Unsupported( 'unsupported_rate', "'{$line->label}' uses a {$rate} % tax: only 21, 10 and 4 % IVA are supported" );
			}
			if ( ! isset( $groups[ $rate ] ) ) {
				$groups[ $rate ] = array( 0, 0 );
			}
			$groups[ $rate ][0] += $net;
			$groups[ $rate ][1] += $taxes[ $rate ];
		}
		if ( ! $groups ) {
			throw new Unsupported( 'nothing_taxable', 'The order has no taxed line to register' );
		}
		return $groups;
	}

	/**
	 * Step 3. Groups in cents whose base + tax add up to what the customer paid.
	 *
	 * @param array<string, array{int, int}> $groups Micros per rate.
	 * @param list<string>                   $notes  Collects what the admin should know.
	 * @return array<string, array{int, int}> Cents per rate.
	 * @throws Unsupported When the difference is more than rounding.
	 */
	private function reconcile( array $groups, int $total, array &$notes ): array {
		$cents = array();
		$sum   = 0;
		foreach ( $groups as $rate => $group ) {
			$cents[ $rate ] = array( Money::micros_to_cents( $group[0] ), Money::micros_to_cents( $group[1] ) );
			$sum           += $cents[ $rate ][0] + $cents[ $rate ][1];
		}
		$difference = $total - $sum;
		if ( 0 === $difference ) {
			return $cents;
		}
		if ( abs( $difference ) > self::ROUNDING_TOLERANCE ) {
			throw new Unsupported(
				'rounding_mismatch',
				'The tax lines add up to ' . Money::format( $sum ) . ' but the order total is ' . Money::format( $total )
			);
		}
		$largest           = $this->largest_group( $cents );
		$cents[ $largest ] = array( $cents[ $largest ][0] + $difference, $cents[ $largest ][1] );
		$notes[] = 'Rounding: ' . Money::format( $difference ) . " added to the {$largest} % base so the invoice adds up to the order total";
		return $cents;
	}

	/**
	 * @param array<string, array{int, int}> $cents Cents per rate.
	 */
	private function largest_group( array $cents ): string {
		$largest = '';
		foreach ( $cents as $rate => $group ) {
			if ( '' === $largest || abs( $group[0] ) > abs( $cents[ $largest ][0] ) ) {
				$largest = (string) $rate;
			}
		}
		return $largest;
	}

	/**
	 * @param array<string, array{int, int}> $cents Cents per rate.
	 * @return list<array{rate: string, base: string, tax: string}> Highest rate first.
	 */
	private function tax_lines( array $cents ): array {
		krsort( $cents, SORT_NUMERIC );
		$lines = array();
		foreach ( $cents as $rate => $group ) {
			$lines[] = array(
				'rate' => (string) $rate,
				'base' => Money::format( $group[0] ),
				'tax'  => Money::format( $group[1] ),
			);
		}
		return $lines;
	}

	/**
	 * @throws Unsupported When the AEAT would reject the number.
	 */
	private function invoice_number( string $number ): string {
		try {
			return InvoiceNumber::check( $number );
		} catch ( InvalidArgumentException $exception ) {
			throw new Unsupported( 'invalid_invoice_number', "Invoice number '{$number}': " . $exception->getMessage() );
		}
	}

	private function description( OrderSnapshot $order, MapperSettings $settings ): string {
		$text = strtr(
			$settings->description_template,
			array(
				'{order_number}' => $order->order_number,
				'{site_name}'    => $settings->site_name,
			)
		);
		$text = trim( $text );
		return $this->truncate( '' === $text ? 'Venta online - pedido ' . $order->order_number : $text, self::DESCRIPTION_MAX );
	}

	private function truncate( string $text, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length ) : substr( $text, 0, $length );
	}
}
