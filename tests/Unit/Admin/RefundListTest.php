<?php
/**
 * RefundList: the refunded orders waiting for a corrective invoice.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit\Admin;

use Huella\Verifactu\Admin\RefundList;
use PHPUnit\Framework\TestCase;

final class RefundListTest extends TestCase {

	public function test_reads_only_order_ids(): void {
		self::assertSame( array(), RefundList::read( 'garbage' ) );
		self::assertSame( array( 3, 5 ), RefundList::read( array( 3, '4', 0, -1, 5, 3, null ) ) );
	}

	public function test_a_refunded_order_moves_to_the_end_once(): void {
		self::assertSame( array( 7 ), RefundList::add( false, 7 ) );
		self::assertSame( array( 7, 9 ), RefundList::add( array( 7 ), 9 ) );
		self::assertSame( array( 9, 7 ), RefundList::add( array( 7, 9 ), 7 ) );
	}

	public function test_keeps_the_newest_twenty(): void {
		$list = range( 1, RefundList::MAX );
		$next = RefundList::add( $list, 100 );
		self::assertCount( RefundList::MAX, $next );
		self::assertSame( 2, $next[0] );
		self::assertSame( 100, $next[ RefundList::MAX - 1 ] );
	}
}
