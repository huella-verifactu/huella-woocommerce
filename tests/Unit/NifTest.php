<?php
/**
 * Nif must agree with huella core on every case of the shared fixture.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit;

use Huella\Verifactu\Nif;
use PHPUnit\Framework\TestCase;

final class NifTest extends TestCase {

	/**
	 * @return iterable<string, array{string, bool}>
	 */
	public static function cases(): iterable {
		$fixture = Fixtures::load( 'nif_cases.json' );
		foreach ( $fixture['valid'] as $case ) {
			yield "valid {$case['nif']}" => array( $case['nif'], true );
		}
		foreach ( $fixture['invalid'] as $case ) {
			yield "invalid {$case['nif']}" => array( $case['nif'], false );
		}
	}

	/**
	 * @dataProvider cases
	 */
	public function test_checksum( string $nif, bool $valid ): void {
		self::assertSame( $valid, Nif::is_valid( $nif ) );
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function normalised(): iterable {
		foreach ( Fixtures::load( 'nif_cases.json' )['normalised'] as $case ) {
			yield $case['input'] => array( $case['input'], $case['nif'] );
		}
	}

	/**
	 * @dataProvider normalised
	 */
	public function test_normalize( string $input, string $nif ): void {
		self::assertSame( $nif, Nif::normalize( $input ) );
		self::assertTrue( Nif::is_valid( $input ) );
	}
}
