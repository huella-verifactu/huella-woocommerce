<?php
/**
 * Qr::url() must build exactly the URLs huella core builds (shared fixture, SPEC 3.7).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit;

use Huella\Verifactu\Qr;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QrTest extends TestCase {

	private const ENVIRONMENTS = array(
		'test'       => 'test',
		'production' => 'live', // Core's name -> the plugin's (the API key decides, D11).
	);

	/**
	 * @return iterable<string, array{array<string, string>}>
	 */
	public static function cases(): iterable {
		foreach ( Fixtures::load( 'qr_cases.json' )['cases'] as $case ) {
			yield $case['name'] => array( $case );
		}
	}

	/**
	 * @dataProvider cases
	 * @param array<string, string> $case One fixture case.
	 */
	public function test_same_url_as_core( array $case ): void {
		$url = Qr::url( $case['nif'], $case['number'], $case['issue_date'], $case['total'], self::ENVIRONMENTS[ $case['environment'] ] );
		self::assertSame( $case['url'], $url );
	}

	/**
	 * @return iterable<string, array{string, string, string, string, string}>
	 */
	public static function invalid(): iterable {
		yield 'bad nif' => array( 'B12345678', '1', '2026-01-20', '1.00', 'test' );
		yield 'forbidden character' => array( 'B12345674', 'A=1', '2026-01-20', '1.00', 'test' );
		yield 'three decimals' => array( 'B12345674', '1', '2026-01-20', '1.000', 'test' );
		yield 'no such date' => array( 'B12345674', '1', '2026-02-30', '1.00', 'test' );
		yield 'date format' => array( 'B12345674', '1', '20-01-2026', '1.00', 'test' );
		yield 'environment' => array( 'B12345674', '1', '2026-01-20', '1.00', 'production' );
	}

	/**
	 * @dataProvider invalid
	 */
	public function test_rejects( string $nif, string $number, string $date, string $total, string $environment ): void {
		$this->expectException( InvalidArgumentException::class );
		Qr::url( $nif, $number, $date, $total, $environment );
	}
}
