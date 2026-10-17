<?php
/**
 * The refunded orders waiting for a corrective invoice, as stored in an option. Pure: tested
 * without WordPress.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Admin;

final class RefundList {

	public const MAX = 20; // The notice names a few; the order notes keep the full record.

	/**
	 * @param mixed $stored The option value; anything else counts as empty.
	 * @return list<int>
	 */
	public static function read( $stored ): array {
		$ids = array();
		foreach ( is_array( $stored ) ? $stored : array() as $value ) {
			if ( is_int( $value ) && $value > 0 && ! in_array( $value, $ids, true ) ) {
				$ids[] = $value;
			}
		}
		return $ids;
	}

	/**
	 * The list with $order_id moved to the end (newest), keeping the last MAX.
	 *
	 * @param mixed $stored The option value.
	 * @return list<int>
	 */
	public static function add( $stored, int $order_id ): array {
		$ids   = array_values( array_diff( self::read( $stored ), array( $order_id ) ) );
		$ids[] = $order_id;
		return array_slice( $ids, -self::MAX );
	}
}
