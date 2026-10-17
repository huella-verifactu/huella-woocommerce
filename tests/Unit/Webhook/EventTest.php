<?php
/**
 * Event, SeenEvents and Registration: how webhook bodies and stored state are read.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit\Webhook;

use Huella\Verifactu\Webhook\Event;
use Huella\Verifactu\Webhook\Registration;
use Huella\Verifactu\Webhook\SeenEvents;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase {

	private const EVENT_ID = 'evt_0123456789abcdef0123456789abcdef';
	private const ISSUER   = 'iss_0123456789abcdef0123456789abcdef';

	/**
	 * @param array<string, mixed> $data
	 */
	private static function body( string $type, array $data, string $id = self::EVENT_ID ): string {
		return (string) json_encode(
			array(
				'id'      => $id,
				'type'    => $type,
				'created' => 1795000000,
				'data'    => $data,
			)
		);
	}

	public function test_a_record_event_names_its_order_record_and_issuer(): void {
		$event = Event::parse(
			self::body(
				'record.accepted',
				array(
					'id'           => 'rec_1',
					'issuer_id'    => self::ISSUER,
					'external_ref' => 'wc_order_1234',
					'status'       => 'accepted',
				)
			)
		);
		self::assertNotNull( $event );
		self::assertSame( self::EVENT_ID, $event->id );
		self::assertTrue( $event->is_record() );
		self::assertSame( 1234, $event->order_id() );
		self::assertSame( 'rec_1', $event->object_id() );
		self::assertSame( self::ISSUER, $event->issuer_id() );
	}

	public function test_an_issuer_event_names_the_issuer_itself(): void {
		$event = Event::parse( self::body( 'issuer.paused', array( 'id' => self::ISSUER ) ) );
		self::assertNotNull( $event );
		self::assertFalse( $event->is_record() );
		self::assertNull( $event->order_id() );
		self::assertSame( self::ISSUER, $event->issuer_id() );
	}

	/**
	 * @dataProvider foreign_refs
	 * @param mixed $ref
	 */
	public function test_only_our_order_refs_name_an_order( $ref ): void {
		$event = Event::parse( self::body( 'record.accepted', array( 'external_ref' => $ref ) ) );
		self::assertNotNull( $event );
		self::assertNull( $event->order_id() );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function foreign_refs(): array {
		return array(
			'none'         => array( null ),
			'other system' => array( 'shopify_1234' ),
			'zero'         => array( 'wc_order_0' ),
			'suffix'       => array( 'wc_order_12x' ),
			'number'       => array( 1234 ),
		);
	}

	/**
	 * @dataProvider not_events
	 */
	public function test_bodies_that_are_not_events( string $body ): void {
		self::assertNull( Event::parse( $body ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function not_events(): array {
		return array(
			'not json'   => array( 'hello' ),
			'a list'     => array( '[1,2]' ),
			'bad id'     => array( self::body( 'record.accepted', array(), 'evt_x' ) ),
			'no data'    => array( '{"id":"' . self::EVENT_ID . '","type":"record.accepted"}' ),
			'type array' => array( '{"id":"' . self::EVENT_ID . '","type":[],"data":{}}' ),
		);
	}

	public function test_seen_events_are_applied_once_and_kept_to_500(): void {
		$seen = SeenEvents::add( 'garbage', 'evt_a' );
		self::assertSame( array( 'evt_a' ), $seen );
		self::assertNull( SeenEvents::add( $seen, 'evt_a' ) );

		$full = array();
		for ( $n = 0; $n < SeenEvents::MAX; $n++ ) {
			$full[] = "evt_{$n}";
		}
		$after = SeenEvents::add( $full, 'evt_new' );
		self::assertNotNull( $after );
		self::assertCount( SeenEvents::MAX, $after );
		self::assertSame( 'evt_1', $after[0] ); // The oldest went.
		self::assertSame( 'evt_new', $after[ SeenEvents::MAX - 1 ] );
	}

	public function test_registration_is_kept_while_url_and_key_are_the_same(): void {
		$url    = 'https://tienda.example/wp-json/huella/v1/events';
		$key    = Registration::key_hash( 'hk_test_abc' );
		$stored = Registration::from_answer(
			array(
				'id'     => 'whe_0123456789abcdef0123456789abcdef',
				'secret' => 'whsec_' . str_repeat( 'A', 43 ),
			),
			$url,
			$key
		);
		self::assertNotNull( $stored );
		self::assertTrue( Registration::is_current( $stored, $url, $key ) );
		self::assertFalse( Registration::is_current( $stored, 'https://nueva.example/wp-json/huella/v1/events', $key ) );
		self::assertFalse( Registration::is_current( $stored, $url, Registration::key_hash( 'hk_live_abc' ) ) );
		self::assertFalse( Registration::is_current( false, $url, $key ) );
		self::assertFalse( Registration::is_current( array( 'id' => 'whe_1' ), $url, $key ) );
	}

	/**
	 * @dataProvider bad_answers
	 * @param array<string, mixed> $answer
	 */
	public function test_invalid_answers_are_not_stored( array $answer ): void {
		self::assertNull( Registration::from_answer( $answer, 'https://x.example', 'k' ) );
	}

	/**
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function bad_answers(): array {
		$id     = 'whe_0123456789abcdef0123456789abcdef';
		$secret = 'whsec_' . str_repeat( 'A', 43 );
		return array(
			'no secret'    => array( array( 'id' => $id ) ),
			'short secret' => array(
				array(
					'id'     => $id,
					'secret' => 'whsec_x',
				),
			),
			'bad id'       => array(
				array(
					'id'     => 'rec_1',
					'secret' => $secret,
				),
			),
		);
	}
}
