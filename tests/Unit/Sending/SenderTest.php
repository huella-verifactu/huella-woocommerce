<?php
/**
 * Sender (SPEC 6.6): when an order is sent, stored, retried or left for a person.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit\Sending;

use Huella\Verifactu\Api\ApiClient;
use Huella\Verifactu\Invoice\Customer;
use Huella\Verifactu\Invoice\MapperSettings;
use Huella\Verifactu\Invoice\OrderLine;
use Huella\Verifactu\Invoice\OrderSnapshot;
use Huella\Verifactu\Sending\RetryPolicy;
use Huella\Verifactu\Sending\Sender;
use Huella\Verifactu\Sending\Status;
use Huella\Verifactu\Tests\Unit\FakeHttp;
use Huella\Verifactu\Tests\Unit\FakeOrder;
use Huella\Verifactu\Tests\Unit\FakeScheduler;
use PHPUnit\Framework\TestCase;

final class SenderTest extends TestCase {

	private const KEY = 'hk_test_abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG';

	/** @var FakeHttp */
	private $http;
	/** @var FakeScheduler */
	private $scheduler;

	protected function setUp(): void {
		$this->http      = new FakeHttp();
		$this->scheduler = new FakeScheduler();
	}

	private static function order( string $total = '100.00' ): FakeOrder {
		return new FakeOrder(
			new OrderSnapshot(
				42,
				'42',
				'FAC-2026-1',
				'2026-11-20',
				$total,
				array( new OrderLine( 'Camiseta', '82.64', array( '21' => '17.36' ) ) ),
				new Customer( 'Ana', '', 'ES' )
			)
		);
	}

	private function sender( bool $configured = true ): Sender {
		$api      = $configured ? new ApiClient( $this->http, self::KEY, 'https://api.example.test' ) : null;
		$settings = $configured ? new MapperSettings( 'iss_1', '400.00', 'Pedido {order_number}', 'Tienda' ) : null;
		return new Sender( $api, $settings, $this->scheduler );
	}

	public function test_registers_and_stores_the_record(): void {
		$this->http->answer(
			201,
			array(
				'id'     => 'rec_1',
				'status' => 'queued',
			)
		);
		$order = self::order();
		$this->sender()->send( $order );
		self::assertSame( array( 'rec_1', 'queued' ), array( $order->record_id, $order->status ) );
		$body = json_decode( (string) $this->http->requests[0]['body'], true );
		self::assertIsArray( $body );
		self::assertSame( array( 'FAC-2026-1', 'wc_order_42' ), array( $body['series_number'], $body['external_ref'] ) );
	}

	public function test_an_identical_replay_is_also_a_registration(): void {
		$this->http->answer(
			200,
			array(
				'id'     => 'rec_1',
				'status' => 'accepted',
			)
		);
		$order = self::order();
		$this->sender()->send( $order );
		self::assertSame( 'rec_1', $order->record_id );
	}

	public function test_does_nothing_twice_or_before_the_invoice(): void {
		$registered            = self::order();
		$registered->record_id = 'rec_1';
		$this->sender()->send( $registered );
		$this->sender()->send( new FakeOrder( null ) );
		self::assertSame( array(), $this->http->requests );
	}

	public function test_not_configured(): void {
		$order = self::order();
		$this->sender( false )->send( $order );
		self::assertSame( Status::NOT_CONFIGURED, $order->status );
		self::assertSame( array(), $this->scheduler->later );
	}

	public function test_out_of_scope_orders_are_never_sent(): void {
		$order = self::order( '500.00' ); // Lines say 100.00: rounding_mismatch.
		$this->sender()->send( $order );
		self::assertSame( array( Status::UNSUPPORTED, 'rounding_mismatch' ), array( $order->status, $order->code ) );
		self::assertSame( array(), $this->http->requests );
	}

	public function test_a_refusal_waits_for_a_person(): void {
		$this->http->answer(
			409,
			array(
				'code'  => 'invoice_number_conflict',
				'title' => 'Number already used',
			)
		);
		$order = self::order();
		$this->sender()->send( $order );
		self::assertSame( array( Status::ERROR, 'invoice_number_conflict' ), array( $order->status, $order->code ) );
		self::assertSame( array(), $this->scheduler->later );
	}

	public function test_field_errors_reach_the_message(): void {
		$this->http->answer(
			422,
			array(
				'code'   => 'validation_error',
				'title'  => 'Invalid invoice',
				'errors' => array(
					array(
						'field'   => 'issue_date',
						'message' => 'must not be in the future',
					),
				),
			)
		);
		$order = self::order();
		$this->sender()->send( $order );
		self::assertSame( 'Invalid invoice (issue_date: must not be in the future)', $order->message );
	}

	public function test_no_answer_schedules_a_retry(): void {
		$this->http->no_answer();
		$order = self::order();
		$this->sender()->send( $order );
		self::assertSame( array( Status::RETRYING, 1 ), array( $order->status, $order->failures ) );
		self::assertSame( array( array( 42, 60 ) ), $this->scheduler->later );
	}

	public function test_after_48_hours_it_stops_and_says_so(): void {
		$this->http->no_answer();
		$order           = self::order();
		$order->failures = 53; // 5 quick tries + 48 hourly ones.
		$this->sender()->send( $order );
		self::assertSame( array( Status::ERROR, 'gave_up', 54 ), array( $order->status, $order->code, $order->failures ) );
		self::assertSame( array(), $this->scheduler->later );
	}

	public function test_an_answer_without_a_record_id_is_not_a_registration(): void {
		$this->http->answer( 201, array( 'status' => 'queued' ) );
		$order = self::order();
		$this->sender()->send( $order );
		self::assertNull( $order->record_id );
		self::assertSame( array( Status::ERROR, 'unexpected_answer' ), array( $order->status, $order->code ) );
	}

	public function test_only_expected_fields_are_kept(): void {
		$this->http->answer(
			201,
			array(
				'id'          => 'rec_0123abcd',
				'status'      => '<script>',
				'environment' => 'prod',
				'huella'      => 'not a hash',
				'qr_url'      => 'https://evil.example/ValidarQR',
				'extra'       => 'ignored',
			)
		);
		$order = self::order();
		$this->sender()->send( $order );
		self::assertSame(
			array(
				'id'          => 'rec_0123abcd',
				'status'      => 'queued',
				'environment' => 'test',
				'huella'      => '',
				'qr_url'      => '',
			),
			$order->record
		);
	}

	public function test_long_error_text_is_bounded(): void {
		$this->http->answer(
			422,
			array(
				'code'  => 'validation_error',
				'title' => str_repeat( 'x', 2000 ),
			)
		);
		$order = self::order();
		$this->sender()->send( $order );
		self::assertSame( 500, strlen( (string) $order->message ) );
	}

	public function test_retry_schedule(): void {
		$delays = array();
		for ( $failures = 0; $failures <= 54; $failures++ ) {
			$delays[] = RetryPolicy::delay_after( $failures );
		}
		self::assertSame( array( 0, 60, 120, 300, 600, 1800 ), array_slice( $delays, 0, 6 ) );
		self::assertSame( array_fill( 0, 48, 3600 ), array_slice( $delays, 6, 48 ) );
		self::assertNull( $delays[54] );
	}
}
