<?php
/**
 * Background jobs. Implemented with Action Scheduler (bundled with WooCommerce).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Sending;

interface Scheduler {

	/**
	 * Send this order soon, in the background. Does nothing if a send is already queued.
	 */
	public function send_soon( int $order_id ): void;

	/**
	 * Send this order again after ``$seconds``.
	 */
	public function send_later( int $order_id, int $seconds ): void;
}
