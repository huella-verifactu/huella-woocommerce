<?php
/**
 * Fixture files shared with huella core (CI checks they match the pinned core release).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit;

final class Fixtures {

	/**
	 * @return array<string, mixed>
	 */
	public static function load( string $name ): array {
		$data = json_decode( (string) file_get_contents( dirname( __DIR__ ) . "/fixtures/{$name}" ), true, 512, JSON_THROW_ON_ERROR );
		return is_array( $data ) ? $data : array();
	}
}
