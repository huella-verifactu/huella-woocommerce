<?php
/**
 * The last 500 event IDs, so an event delivered twice is applied once (SPEC 6.9). Huella Cloud
 * delivers at least once: a retry after a lost answer repeats the same event ID. Pure: the
 * caller keeps the list in an option.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Webhook;

final class SeenEvents {

	public const MAX = 500;

	/**
	 * The list with $id added (oldest dropped past MAX), or null when $id was already in it.
	 *
	 * @param mixed $stored The list as stored; anything else counts as empty.
	 * @return list<string>|null
	 */
	public static function add( $stored, string $id ): ?array {
		$seen = array();
		foreach ( is_array( $stored ) ? $stored : array() as $value ) {
			if ( is_string( $value ) ) {
				$seen[] = $value;
			}
		}
		if ( in_array( $id, $seen, true ) ) {
			return null;
		}
		$seen[] = $id;
		return array_slice( $seen, -self::MAX );
	}
}
