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

	public function send_later( int $order_id, int $seconds ): void {
		as_schedule_single_action( time() + $seconds, self::SEND, array( $order_id ), self::GROUP );
	}
}
