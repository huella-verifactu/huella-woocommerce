<?php
/**
 * Test double: records the jobs it is asked to schedule.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit;

use Huella\Verifactu\Sending\Scheduler;

final class FakeScheduler implements Scheduler {

	/** @var list<int> */
	public $soon = array();
	/** @var list<array{int, int}> Order ID, delay. */
	public $later = array();

	public function send_soon( int $order_id ): void {
		$this->soon[] = $order_id;
	}

	public function send_later( int $order_id, int $seconds ): void {
		$this->later[] = array( $order_id, $seconds );
	}
}
