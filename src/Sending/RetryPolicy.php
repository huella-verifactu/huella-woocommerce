<?php
/**
 * When to try again after Huella Cloud could not be reached (SPEC 6.6): after 1, 2, 5, 10 and
 * 30 minutes, then every hour for 48 hours, then stop and alert. Repeating is always safe: the
 * invoice number makes the registration idempotent (SPEC 5.4).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Sending;

final class RetryPolicy {

	private const FIRST_DELAYS = array( 60, 120, 300, 600, 1800 ); // Seconds.
	private const HOURLY       = 3600;
	private const HOURLY_TRIES = 48;

	/**
	 * Seconds to wait before the next try, after ``$failures`` failed tries; null to give up.
	 */
	public static function delay_after( int $failures ): ?int {
		if ( $failures < 1 ) {
			return 0;
		}
		if ( $failures <= count( self::FIRST_DELAYS ) ) {
			return self::FIRST_DELAYS[ $failures - 1 ];
		}
		return $failures <= count( self::FIRST_DELAYS ) + self::HOURLY_TRIES ? self::HOURLY : null;
	}
}
