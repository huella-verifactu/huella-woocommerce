<?php
/**
 * Polling fallback for record states (SPEC 6.9): every 15 minutes, orders whose record is still
 * queued or sending (last 7 days) ask Huella Cloud for news. The webhook receiver joins once the
 * cloud sends webhooks (SPEC 5.8); polling stays as the safety net.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Woo;

use Huella\Verifactu\Api\ApiError;
use Huella\Verifactu\Sending\Sender;
use Huella\Verifactu\Sending\StatusNote;
use WC_Order;

final class StatusSync {

	public const HOOK      = 'huella_verifactu_sync';
	public const EVERY     = 900; // Seconds.
	private const DAYS     = 7;
	private const PER_RUN  = 50;
	private const IN_FLIGHT = array( 'queued', 'sending' );

	public static function register(): void {
		add_action(
			self::HOOK,
			static function (): void {
				self::run();
			}
		);
		add_action( 'action_scheduler_init', array( self::class, 'schedule' ) );
	}

	public static function schedule(): void {
		if ( false === as_has_scheduled_action( self::HOOK, array(), ActionSchedulerJobs::GROUP ) ) {
			as_schedule_recurring_action( time() + self::EVERY, self::EVERY, self::HOOK, array(), ActionSchedulerJobs::GROUP );
		}
	}

	/**
	 * @return int How many orders changed state.
	 */
	public static function run(): int {
		$api = Options::api();
		if ( null === $api ) {
			return 0;
		}
		$orders  = wc_get_orders(
			array(
				'limit'        => self::PER_RUN,
				'date_created' => '>' . ( time() - self::DAYS * DAY_IN_SECONDS ),
				'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded: 50 orders, 7 days
					array(
						'key'     => Meta::STATUS,
						'value'   => self::IN_FLIGHT,
						'compare' => 'IN',
					),
				),
			)
		);
		$changed = 0;
		foreach ( is_array( $orders ) ? $orders : array() as $order ) {
			$record_id = (string) $order->get_meta( Meta::RECORD_ID );
			if ( '' === $record_id ) {
				continue;
			}
			try {
				$record = $api->invoice( $record_id );
			} catch ( ApiError $error ) {
				wc_get_logger()->warning( "Huella: status of order {$order->get_id()}: " . $error->getMessage(), array( 'source' => 'huella-verifactu' ) );
				continue;
			}
			$changed += self::apply( $order, $record ) ? 1 : 0;
		}
		return $changed;
	}

	/**
	 * Store a newer state and tell the shop. True if the state changed.
	 *
	 * @param array<string, mixed> $record The record from GET /v1/invoices/{id}.
	 */
	public static function apply( WC_Order $order, array $record ): bool {
		$status = is_string( $record['status'] ?? null ) ? $record['status'] : '';
		if ( ! in_array( $status, Sender::RECORD_STATUSES, true ) || $status === (string) $order->get_meta( Meta::STATUS ) ) {
			return false;
		}
		$order->update_meta_data( Meta::STATUS, $status );
		$order->save();
		$note = StatusNote::for_state( $status, is_array( $record['aeat'] ?? null ) ? $record['aeat'] : array() );
		if ( null !== $note ) {
			$order->add_order_note( sanitize_text_field( $note ) );
		}
		return true;
	}
}
