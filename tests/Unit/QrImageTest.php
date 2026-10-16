<?php
/**
 * QrImage: same symbol as huella core, the AEAT size rules, PNG and SVG.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit;

use Huella\Verifactu\QrImage;
use PHPUnit\Framework\TestCase;

final class QrImageTest extends TestCase {

	/**
	 * Module counts huella core (segno, level M, no boost) gives the shared fixture URLs, checked
	 * on 16 Oct: version 7 (45 modules) for pre-production URLs, 8 (49) for the longer production host.
	 *
	 * @return iterable<string, array{string, int}>
	 */
	public static function core_versions(): iterable {
		foreach ( Fixtures::load( 'qr_cases.json' )['cases'] as $case ) {
			yield $case['name'] => array( $case['url'], 'production' === $case['environment'] ? 49 : 45 );
		}
	}

	/**
	 * The same QR version as core, except exactly at a version's capacity: a 122-byte URL fills
	 * version 7-M to the last byte; core (segno) fits it there, php-qrcode moves to version 8.
	 * Both are valid level-M codes of the same URL (the AEAT fixes the level, not the version),
	 * and the printed symbol stays 35 mm because the size follows the real module count.
	 *
	 * @dataProvider core_versions
	 */
	public function test_same_symbol_size_as_core( string $url, int $modules ): void {
		$expected = 122 === strlen( $url ) ? $modules + 4 : $modules;
		self::assertSame( $expected, QrImage::symbol_modules( $url ) );
	}

	/**
	 * @requires extension gd
	 */
	public function test_png_at_print_resolution(): void {
		$url   = 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=B12345674&numserie=2026%2F000123&fecha=20-01-2026&importe=100.00';
		$image = QrImage::for_url( $url, true );
		self::assertStringStartsWith( 'data:image/png;base64,', $image->data_uri );
		$png  = (string) base64_decode( substr( $image->data_uri, strlen( 'data:image/png;base64,' ) ), true );
		$info = getimagesizefromstring( $png );
		self::assertIsArray( $info );
		self::assertSame( $info[0], $info[1] );
		self::assertSame( 0, $info[0] % ( 45 + 8 ), 'whole pixels per module, quiet zone included' );
		$dpi = $info[0] / ( $image->size_mm / 25.4 );
		self::assertGreaterThanOrEqual( 300, (int) round( $dpi ) );
		// The symbol is 35 mm; the 4-module quiet zone on each side comes on top.
		self::assertEqualsWithDelta( 35 * 53 / 45, $image->size_mm, 0.01 );
	}

	public function test_svg_when_gd_is_missing(): void {
		$image = QrImage::for_url( 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=B12345674', false );
		self::assertStringStartsWith( 'data:image/svg+xml;base64,', $image->data_uri );
		self::assertStringContainsString( '<svg', (string) base64_decode( substr( $image->data_uri, strlen( 'data:image/svg+xml;base64,' ) ), true ) );
	}
}
