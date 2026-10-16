<?php
/**
 * Scheduler on Action Scheduler, which ships with WooCommerce. Jobs run in the background
 * (WP-Cron or the async runner), never in the customer's request.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Woo;

use Huella\Verifactu\Sending\Scheduler;

final class ActionSchedulerJobs implements Scheduler {

	public const SEND  = 'huella_verifactu_send';
	public const GROUP = 'huella-verifactu';

	public function send_soon( int $order_id ): void {
		$args = array( $order_id );
		if ( false === as_has_scheduled_action( self::SEND, $args, self::GROUP ) ) {
			as_enqueue_async_action( self::SEND, $args, self::GROUP );
		}
	}

	/**
	 * Called from inside the running job, so only *pending* actions count here:
	 * as_has_scheduled_action() also counts the running one, and the retry would never be set.
	 */
	public function send_later( int $order_id, int $seconds ): void {
		$args    = array( $order_id );
		$pending = as_get_scheduled_actions(
			array(
				'hook'     => self::SEND,
				'args'     => $args,
				'group'    => self::GROUP,
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 1,
			),
			'ids'
		);
		if ( ! $pending ) {
			as_schedule_single_action( time() + $seconds, self::SEND, $args, self::GROUP );
		}
	}
}
