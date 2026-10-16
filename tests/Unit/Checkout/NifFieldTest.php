<?php
/**
 * Checkout validation of the optional NIF/CIF field.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit\Checkout;

use Huella\Verifactu\Checkout\NifField;
use PHPUnit\Framework\TestCase;

final class NifFieldTest extends TestCase {

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function accepted(): iterable {
		yield 'empty: the field is optional' => array( '', 'ES' );
		yield 'Spanish CIF with separators' => array( 'b-1234567.4', 'ES' );
		yield 'Spanish NIE' => array( 'X1234567L', 'ES' );
		yield 'French VAT, French address' => array( 'FR12345678901', 'FR' );
		yield 'VAT number, country unknown (block checkout)' => array( 'DE123456789', '' );
	}

	/**
	 * @dataProvider accepted
	 */
	public function test_accepted( string $value, string $country ): void {
		self::assertNull( NifField::problem( $value, $country ) );
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function refused(): iterable {
		yield 'wrong check letter' => array( '12345678A', 'ES' );
		yield 'VAT-looking value for a Spanish address' => array( 'DE123456789', 'ES' );
		yield 'Spanish VAT prefix (send the NIF itself)' => array( 'ESB12345674', '' );
		yield 'nonsense' => array( 'hola', 'ES' );
	}

	/**
	 * @dataProvider refused
	 */
	public function test_refused( string $value, string $country ): void {
		self::assertNotNull( NifField::problem( $value, $country ) );
	}
}
