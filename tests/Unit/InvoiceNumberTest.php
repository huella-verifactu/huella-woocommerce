<?php
/**
 * InvoiceNumber applies huella's NumSerieFactura rules.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit;

use Huella\Verifactu\InvoiceNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InvoiceNumberTest extends TestCase {

	public function test_accepts_typical_numbers(): void {
		foreach ( array( '2026-000123', 'FAC/2026/1', '12345678&G33', str_repeat( 'A', 60 ) ) as $number ) {
			self::assertSame( $number, InvoiceNumber::check( $number ) );
		}
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function invalid(): iterable {
		yield 'empty' => array( '' );
		yield 'too long' => array( str_repeat( 'A', 61 ) );
		yield 'leading space' => array( ' 1' );
		yield 'trailing space' => array( '1 ' );
		yield 'non-ascii' => array( "FACTURA-\u{00D1}" );
		yield 'tab' => array( "A\tB" );
		yield 'quote' => array( 'A"B' );
		yield 'apostrophe' => array( "A'B" );
		yield 'less than' => array( 'A<B' );
		yield 'greater than' => array( 'A>B' );
		yield 'equals' => array( 'A=B' );
	}

	/**
	 * @dataProvider invalid
	 */
	public function test_rejects( string $number ): void {
		$this->expectException( InvalidArgumentException::class );
		InvoiceNumber::check( $number );
	}
}
