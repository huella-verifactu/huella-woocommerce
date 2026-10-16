<?php
/**
 * Money rounds like huella's round_amount() (half away from zero) on every PHP version.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit;

use Huella\Verifactu\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase {

	/**
	 * @return iterable<string, array{string|int|float, string}>
	 */
	public static function amounts(): iterable {
		yield 'two decimals' => array( '17.36', '17.36' );
		yield 'half up' => array( '0.285', '0.29' );
		yield 'half away from zero, negative' => array( '-0.285', '-0.29' );
		yield 'below half' => array( '0.2849999', '0.28' );
		yield 'long tail' => array( '82.6446281', '82.64' );
		yield 'integer string' => array( '100', '100.00' );
		yield 'one decimal' => array( '123.4', '123.40' );
		yield 'float that is 0.28499999... in binary' => array( 0.285, '0.29' );
		yield 'float sum' => array( 0.1 + 0.2, '0.30' );
		yield 'int' => array( 7, '7.00' );
		yield 'small negative' => array( '-0.05', '-0.05' );
		yield 'spaces' => array( ' 4.00 ', '4.00' );
		yield 'twelve integer digits' => array( '999999999999.99', '999999999999.99' );
	}

	/**
	 * @dataProvider amounts
	 * @param string|int|float $value Input amount.
	 */
	public function test_text( $value, string $expected ): void {
		self::assertSame( $expected, Money::text( $value ) );
	}

	public function test_cents_add_exactly(): void {
		$sum = Money::to_cents( '0.10' ) + Money::to_cents( '0.20' );
		self::assertSame( '0.30', Money::format( $sum ) );
	}

	/**
	 * @return iterable<string, array{string|float}>
	 */
	public static function invalid(): iterable {
		yield 'text' => array( 'abc' );
		yield 'comma decimal' => array( '1,50' );
		yield 'exponent' => array( '1e3' );
		yield 'infinite' => array( INF );
		yield 'thirteen integer digits' => array( '1000000000000.00' );
	}

	/**
	 * @dataProvider invalid
	 * @param string|float $value Bad input.
	 */
	public function test_rejects( $value ): void {
		$this->expectException( InvalidArgumentException::class );
		Money::text( $value );
	}
}
