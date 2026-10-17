<?php
/**
 * Webhooks from Huella Cloud (SPEC 6.9): POST /wp-json/huella/v1/events.
 *
 * The URL is registered with Huella when the connection is checked (settings page). Each event
 * is accepted only with a valid Huella-Signature, applied once (the last 500 IDs are kept), and
 * only to the order whose stored record ID it names: another shop of the same account may use
 * the same order numbers. Polling every 15 minutes (StatusSync) stays as the safety net, so a
 * missed or refused webhook only delays a status, never loses it.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Woo;

use Huella\Verifactu\Api\ApiClient;
use Huella\Verifactu\Api\ApiError;
use Huella\Verifactu\Webhook\Event;
use Huella\Verifactu\Webhook\Registration;
use Huella\Verifactu\Webhook\SeenEvents;
use Huella\Verifactu\Webhook\Signature;
use WC_Order;
use WP_REST_Request;
use WP_REST_Response;

final class Webhooks {

	public const OPTION    = 'huella_verifactu_webhook';      // Registration (with the secret).
	public const SEEN      = 'huella_verifactu_seen_events';  // Last 500 event IDs.
	public const PAUSED    = 'huella_verifactu_issuer_paused'; // Unix time of issuer.paused.
	private const NS       = 'huella/v1';
	private const ROUTE    = '/events';

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			self::NS,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'receive' ),
				'permission_callback' => array( self::class, 'signed' ),
			)
		);
	}

	public static function url(): string {
		return rest_url( self::NS . self::ROUTE );
	}

	/**
	 * permission_callback: only Huella, signing with this shop's secret, gets in.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The webhook.
	 */
	public static function signed( WP_REST_Request $request ): bool {
		$registration = Registration::read( get_option( self::OPTION ) );
		if ( null === $registration ) {
			return false;
		}
		$header = (string) $request->get_header( 'huella_signature' );
		return Signature::verify( $header, $request->get_body(), $registration['secret'], time() );
	}

	/**
	 * @param WP_REST_Request<array<string, mixed>> $request The webhook, signature checked.
	 */
	public static function receive( WP_REST_Request $request ): WP_REST_Response {
		$event = Event::parse( $request->get_body() );
		if ( null === $event ) {
			return new WP_REST_Response( array( 'received' => false ), 400 );
		}
		$seen = SeenEvents::add( get_option( self::SEEN, array() ), $event->id );
		if ( null !== $seen ) {
			update_option( self::SEEN, $seen, false );
			self::apply( $event );
		}
		// 200 even for events about other shops or unknown orders: a retry would not help.
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	private static function apply( Event $event ): void {
		$ours = '' !== $event->issuer_id() && Options::get( 'issuer_id' ) === $event->issuer_id();
		if ( 'issuer.paused' === $event->type ) {
			if ( $ours ) {
				update_option( self::PAUSED, time(), false );
			}
			return;
		}
		$order_id = $event->order_id();
		$order    = null === $order_id ? null : wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || $event->object_id() !== (string) $order->get_meta( Meta::RECORD_ID ) ) {
			return;
		}
		StatusSync::apply( $order, $event->data );
		if ( $ours && 'record.rejected' !== $event->type ) {
			delete_option( self::PAUSED ); // A record went through: sending has resumed.
		}
	}

	/**
	 * Make sure Huella knows where to send events. True when webhooks are on; false means
	 * polling only (for example a local site Huella cannot reach).
	 */
	public static function ensure( ApiClient $api ): bool {
		$url = self::url();
		$key = Registration::key_hash( Options::api_key() );
		if ( Registration::is_current( get_option( self::OPTION ), $url, $key ) ) {
			return true;
		}
		try {
			$registration = Registration::from_answer( $api->register_webhook( $url, Event::SUBSCRIBED ), $url, $key );
		} catch ( ApiError $error ) {
			$registration = null;
			wc_get_logger()->info( 'Huella: webhooks not registered (' . $error->getMessage() . '); order states are polled every 15 minutes instead.', array( 'source' => 'huella-verifactu' ) );
		}
		if ( null === $registration ) {
			delete_option( self::OPTION );
			return false;
		}
		update_option( self::OPTION, $registration, false );
		return true;
	}

	/**
	 * When Huella reported the chosen issuer paused (certificate refused), or null.
	 */
	public static function paused_since(): ?int {
		$since = get_option( self::PAUSED );
		return is_numeric( $since ) ? (int) $since : null;
	}
}
