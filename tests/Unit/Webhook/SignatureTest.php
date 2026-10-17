<?php
/**
 * Signature: the Huella-Signature check (SPEC 5.8). The vector is computed the way Huella
 * Cloud signs (app/services/webhooks.py sign()).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit\Webhook;

use Huella\Verifactu\Webhook\Signature;
use PHPUnit\Framework\TestCase;

final class SignatureTest extends TestCase {

	private const SECRET = 'whsec_testtesttesttesttesttesttesttest';
	private const BODY   = '{"id":"evt_1","type":"record.accepted"}';
	private const T      = 1795000000;

	private static function header( string $secret = self::SECRET, int $t = self::T, string $body = self::BODY ): string {
		return 't=' . $t . ',v1=' . hash_hmac( 'sha256', $t . '.' . $body, $secret );
	}

	public function test_a_valid_signature_passes(): void {
		self::assertTrue( Signature::verify( self::header(), self::BODY, self::SECRET, self::T ) );
		self::assertTrue( Signature::verify( self::header(), self::BODY, self::SECRET, self::T + 300 ) );
		self::assertTrue( Signature::verify( self::header(), self::BODY, self::SECRET, self::T - 300 ) );
	}

	public function test_the_cloud_vector(): void {
		// Same inputs as tests/test_webhooks.py::test_signature_is_hmac_sha256_of_timestamp_dot_body.
		$expected = hash_hmac( 'sha256', '1795000000.{"id":"evt_1"}', 'whsec_x' );
		self::assertTrue( Signature::verify( "t=1795000000,v1={$expected}", '{"id":"evt_1"}', 'whsec_x', 1795000000 ) );
	}

	public function test_an_old_or_future_event_is_refused(): void {
		self::assertFalse( Signature::verify( self::header(), self::BODY, self::SECRET, self::T + 301 ) );
		self::assertFalse( Signature::verify( self::header(), self::BODY, self::SECRET, self::T - 301 ) );
	}

	public function test_a_changed_body_or_other_secret_is_refused(): void {
		self::assertFalse( Signature::verify( self::header(), self::BODY . ' ', self::SECRET, self::T ) );
		self::assertFalse( Signature::verify( self::header( 'whsec_other' ), self::BODY, self::SECRET, self::T ) );
		self::assertFalse( Signature::verify( self::header(), self::BODY, '', self::T ) );
	}

	/**
	 * @dataProvider malformed_headers
	 */
	public function test_malformed_headers_are_refused( string $header ): void {
		self::assertFalse( Signature::verify( $header, self::BODY, self::SECRET, self::T ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function malformed_headers(): array {
		$v1 = hash_hmac( 'sha256', self::T . '.' . self::BODY, self::SECRET );
		return array(
			'empty'           => array( '' ),
			'no timestamp'    => array( "v1={$v1}" ),
			'no signature'    => array( 't=' . self::T ),
			'text timestamp'  => array( "t=soon,v1={$v1}" ),
			'negative time'   => array( 't=-' . self::T . ",v1={$v1}" ),
			'uppercase hex'   => array( 't=' . self::T . ',v1=' . strtoupper( $v1 ) ),
			'other scheme'    => array( 't=' . self::T . ",v0={$v1}" ),
		);
	}

	public function test_any_of_several_signatures_may_match(): void {
		$header = 't=' . self::T . ',v1=deadbeef,' . explode( ',', self::header() )[1];
		self::assertTrue( Signature::verify( $header, self::BODY, self::SECRET, self::T ) );
	}
}
