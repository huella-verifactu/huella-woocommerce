<?php
/**
 * PayloadMapper (SPEC 6.5) on fixture orders.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit\Invoice;

use Huella\Verifactu\Invoice\Customer;
use Huella\Verifactu\Invoice\Mapped;
use Huella\Verifactu\Invoice\MapperSettings;
use Huella\Verifactu\Invoice\OrderLine;
use Huella\Verifactu\Invoice\OrderSnapshot;
use Huella\Verifactu\Invoice\PayloadMapper;
use Huella\Verifactu\Invoice\Unsupported;
use PHPUnit\Framework\TestCase;

final class PayloadMapperTest extends TestCase {

	private const ISSUER = 'iss_0123456789abcdef0123456789abcdef';

	/**
	 * @param list<OrderLine> $lines Order lines.
	 */
	private static function order( array $lines, string $total, ?Customer $customer = null, string $number = 'FAC-2026-000123' ): OrderSnapshot {
		return new OrderSnapshot( 1234, '1234', $number, '2026-11-20', $total, $lines, $customer ?? new Customer( 'Ana García', '', 'ES' ) );
	}

	private static function product( string $net, string $tax, string $rate = '21', string $label = 'Camiseta' ): OrderLine {
		return new OrderLine( $label, $net, array( $rate => $tax ) );
	}

	private static function map( OrderSnapshot $order, string $template = 'Venta online - pedido {order_number}' ): Mapped {
		return ( new PayloadMapper() )->map( $order, new MapperSettings( self::ISSUER, '400.00', $template, 'Tienda Uno' ) );
	}

	private function expect_unsupported( string $reason, OrderSnapshot $order ): void {
		try {
			self::map( $order );
		} catch ( Unsupported $exception ) {
			self::assertSame( $reason, $exception->reason, $exception->getMessage() );
			return;
		}
		self::fail( "Expected Unsupported({$reason})" );
	}

	public function test_simplified_invoice(): void {
		$mapped = self::map( self::order( array( self::product( '82.64', '17.36' ) ), '100.00' ) );
		self::assertSame(
			array(
				'issuer_id'     => self::ISSUER,
				'series_number' => 'FAC-2026-000123',
				'issue_date'    => '2026-11-20',
				'type'          => 'F2',
				'description'   => 'Venta online - pedido 1234',
				'tax_lines'     => array(
					array(
						'rate' => '21.00',
						'base' => '82.64',
						'tax'  => '17.36',
					),
				),
				'total'         => '100.00',
				'external_ref'  => 'wc_order_1234',
			),
			$mapped->payload
		);
		self::assertSame( array(), $mapped->notes );
	}

	public function test_a_valid_nif_makes_a_full_invoice(): void {
		$customer = new Customer( 'ACME SL', ' b-1234567.4 ', 'ES' );
		$payload  = self::map( self::order( array( self::product( '82.64', '17.36' ) ), '100.00', $customer ) )->payload;
		self::assertSame( 'F1', $payload['type'] );
		self::assertSame(
			array(
				'name' => 'ACME SL',
				'nif'  => 'B12345674',
			),
			$payload['recipient']
		);
	}

	/**
	 * @return iterable<string, array{string, string, string}>
	 */
	public static function eu_vat_numbers(): iterable {
		yield 'France' => array( 'FR', 'FR 12345678901', 'FR12345678901' );
		yield 'Greece uses EL' => array( 'GR', 'el123456789', 'EL123456789' );
	}

	/**
	 * @dataProvider eu_vat_numbers
	 */
	public function test_an_eu_vat_number_makes_a_full_invoice( string $country, string $typed, string $sent ): void {
		$customer = new Customer( 'ACME SAS', $typed, $country );
		$payload  = self::map( self::order( array( self::product( '82.64', '17.36' ) ), '100.00', $customer ) )->payload;
		self::assertSame( 'F1', $payload['type'] );
		self::assertSame(
			array(
				'name'    => 'ACME SAS',
				'country' => $country,
				'id_type' => '02',
				'id'      => $sent,
			),
			$payload['recipient']
		);
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function unusable_tax_ids(): iterable {
		yield 'wrong NIF checksum' => array( 'ES', 'B12345678' );
		yield 'VAT number of another country' => array( 'FR', 'DE123456789' );
		yield 'outside the EU' => array( 'US', 'US123456789' );
		yield 'Spanish VAT prefix' => array( 'ES', 'ESB12345674' );
	}

	/**
	 * @dataProvider unusable_tax_ids
	 */
	public function test_an_unusable_tax_id_falls_back_to_simplified_with_a_note( string $country, string $tax_id ): void {
		$mapped = self::map( self::order( array( self::product( '82.64', '17.36' ) ), '100.00', new Customer( 'X', $tax_id, $country ) ) );
		self::assertSame( 'F2', $mapped->payload['type'] );
		self::assertArrayNotHasKey( 'recipient', $mapped->payload );
		self::assertCount( 1, $mapped->notes );
		self::assertStringContainsString( $tax_id, $mapped->notes[0] );
	}

	public function test_lines_are_grouped_by_rate_highest_first(): void {
		$lines = array(
			self::product( '10.00', '2.10' ),
			self::product( '20.00', '2.00', '10', 'Libro' ),
			new OrderLine( 'Envío', '4.13', array( '21.0000' => '0.87' ) ),
			new OrderLine( 'Envío gratuito', '0', array() ),
		);
		$payload = self::map( self::order( $lines, '39.10' ) )->payload;
		self::assertSame(
			array(
				array(
					'rate' => '21.00',
					'base' => '14.13',
					'tax'  => '2.97',
				),
				array(
					'rate' => '10.00',
					'base' => '20.00',
					'tax'  => '2.00',
				),
			),
			$payload['tax_lines']
		);
	}

	public function test_six_decimal_lines_are_summed_exactly_then_rounded_once(): void {
		// Three products of 9.99 with tax included at 21 %: WooCommerce keeps 8.256198 net each.
		$lines = array_fill( 0, 3, self::product( '8.256198', '1.733802' ) );
		$mapped = self::map( self::order( $lines, '29.97' ) );
		self::assertSame(
			array(
				'rate' => '21.00',
				'base' => '24.77',
				'tax'  => '5.20',
			),
			$mapped->payload['tax_lines'][0]
		);
		self::assertSame( array(), $mapped->notes );
	}

	public function test_a_rounding_difference_goes_into_the_largest_base(): void {
		$lines = array(
			self::product( '50.004', '10.50', '21', 'Grande' ),
			self::product( '9.996', '1.00', '10', 'Pequeño' ),
		);
		// Lines give 50.00 + 10.50 + 10.00 + 1.00 = 71.50; the customer paid 71.51.
		$mapped = self::map( self::order( $lines, '71.51' ) );
		self::assertSame( '50.01', $mapped->payload['tax_lines'][0]['base'] );
		self::assertSame( '10.00', $mapped->payload['tax_lines'][1]['base'] );
		self::assertStringContainsString( '0.01', $mapped->notes[0] );
	}

	public function test_more_than_rounding_is_not_sent(): void {
		$this->expect_unsupported( 'rounding_mismatch', self::order( array( self::product( '82.64', '17.36' ) ), '100.03' ) );
	}

	/**
	 * WooCommerce rounds tax per line by default. 20 lines of 0.10 at 21 % carry 0.02 each:
	 * 0.40 in total, where 21 % of 2.00 is 0.42. The mapper sends what the invoice shows (0.40).
	 * Huella core today allows only +-0.01 per tax line (SPEC 3.3), so the API would refuse this
	 * order: open decision, see the PR.
	 */
	public function test_per_line_rounding_is_sent_as_the_invoice_shows_it(): void {
		$lines   = array_fill( 0, 20, self::product( '0.10', '0.02' ) );
		$payload = self::map( self::order( $lines, '2.40' ) )->payload;
		self::assertSame(
			array(
				'rate' => '21.00',
				'base' => '2.00',
				'tax'  => '0.40',
			),
			$payload['tax_lines'][0]
		);
	}

	/**
	 * @return iterable<string, array{string, list<OrderLine>, string}>
	 */
	public static function unsupported_orders(): iterable {
		yield 'exempt line' => array( 'untaxed_line', array( new OrderLine( 'Curso', '50.00', array() ) ), '50.00' );
		yield 'zero tax' => array( 'untaxed_line', array( new OrderLine( 'Curso', '50.00', array( '0' => '0.00' ) ) ), '50.00' );
		yield 'two rates on a line' => array(
			'multiple_tax_rates',
			array(
				new OrderLine(
					'Pack',
					'10.00',
					array(
						'21' => '2.10',
						'10' => '1.00',
					)
				),
			),
			'13.10',
		);
		yield 'IGIC 7 %' => array( 'unsupported_rate', array( self::product( '10.00', '0.70', '7' ) ), '10.70' );
		yield 'recargo de equivalencia 5.2 %' => array( 'unsupported_rate', array( self::product( '10.00', '0.52', '5.2' ) ), '10.52' );
		yield 'only free lines' => array( 'nothing_taxable', array( new OrderLine( 'Regalo', '0.00', array() ) ), '0.00' );
	}

	/**
	 * @dataProvider unsupported_orders
	 * @param list<OrderLine> $lines Order lines.
	 */
	public function test_unsupported_orders( string $reason, array $lines, string $total ): void {
		$this->expect_unsupported( $reason, self::order( $lines, $total ) );
	}

	public function test_the_simplified_limit(): void {
		$big = array( self::product( '400.00', '84.00' ) );
		$this->expect_unsupported( 'simplified_limit_exceeded', self::order( $big, '484.00' ) );
		$full = self::map( self::order( $big, '484.00', new Customer( 'ACME SL', 'B12345674', 'ES' ) ) );
		self::assertSame( 'F1', $full->payload['type'] );
	}

	public function test_an_invoice_number_the_aeat_rejects(): void {
		$this->expect_unsupported( 'invalid_invoice_number', self::order( array( self::product( '82.64', '17.36' ) ), '100.00', null, 'FAC="1"' ) );
	}

	public function test_description_template(): void {
		$order = self::order( array( self::product( '82.64', '17.36' ) ), '100.00' );
		self::assertSame( 'Pedido 1234 en Tienda Uno', self::map( $order, 'Pedido {order_number} en {site_name}' )->payload['description'] );
		self::assertSame( 'Venta online - pedido 1234', self::map( $order, '  ' )->payload['description'] );
		self::assertSame( 500, mb_strlen( self::map( $order, str_repeat( 'á', 600 ) )->payload['description'] ) );
	}
}
