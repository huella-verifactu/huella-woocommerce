<?php
/**
 * ApiClient: requests it makes and how answers become results or ApiErrors.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit\Api;

use Huella\Verifactu\Api\ApiClient;
use Huella\Verifactu\Api\ApiError;
use Huella\Verifactu\Api\HttpResponse;
use Huella\Verifactu\Tests\Unit\FakeHttp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ApiClientTest extends TestCase {

	private const TEST_KEY = 'hk_test_abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG';
	private const LIVE_KEY = 'hk_live_abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG';

	public function test_create_invoice_posts_json_with_the_key(): void {
		$http   = ( new FakeHttp() )->answer(
			201,
			array(
				'id'     => 'rec_1',
				'status' => 'queued',
			)
		);
		$client = new ApiClient( $http, self::TEST_KEY, 'https://api.example.test/' );

		$record = $client->create_invoice( array( 'series_number' => 'FAC/1' ) );

		self::assertSame( 'rec_1', $record['id'] );
		$request = $http->requests[0];
		self::assertSame( 'POST', $request['method'] );
		self::assertSame( 'https://api.example.test/v1/invoices', $request['url'] );
		self::assertSame( 'Bearer ' . self::TEST_KEY, $request['headers']['Authorization'] );
		self::assertSame( 'application/json', $request['headers']['Content-Type'] );
		self::assertSame( '{"series_number":"FAC/1"}', $request['body'] );
		self::assertSame( ApiClient::TIMEOUT, $request['timeout'] );
	}

	public function test_the_key_decides_the_environment(): void {
		self::assertSame( 'test', ( new ApiClient( new FakeHttp(), self::TEST_KEY, 'https://x' ) )->environment() );
		self::assertSame( 'live', ( new ApiClient( new FakeHttp(), self::LIVE_KEY, 'https://x' ) )->environment() );
	}

	public function test_rejects_something_that_is_not_a_key(): void {
		$this->expectException( InvalidArgumentException::class );
		new ApiClient( new FakeHttp(), 'sk_live_whatever', 'https://x' );
	}

	public function test_issuers_and_me(): void {
		$http   = ( new FakeHttp() )
			->answer(
				200,
				array(
					'object' => 'list',
					'data'   => array( array( 'id' => 'iss_1' ) ),
				)
			)
			->answer( 200, array( 'account' => array( 'name' => 'Tienda' ) ) );
		$client = new ApiClient( $http, self::TEST_KEY, 'https://x' );
		self::assertSame( array( array( 'id' => 'iss_1' ) ), $client->issuers() );
		self::assertSame( 'Tienda', $client->me()['account']['name'] );
		self::assertSame( 'https://x/v1/invoices/rec_%2F1', ( function () use ( $http ): string {
			$http->answer( 200, array( 'id' => 'rec_/1' ) );
			( new ApiClient( $http, self::TEST_KEY, 'https://x' ) )->invoice( 'rec_/1' );
			return $http->requests[2]['url'];
		} )() );
	}

	public function test_a_problem_becomes_an_api_error_with_its_fields(): void {
		$http = ( new FakeHttp() )->answer(
			422,
			array(
				'code'   => 'validation_error',
				'title'  => 'Invalid invoice',
				'errors' => array(
					array(
						'field'   => 'tax_lines.0',
						'message' => 'bad tax',
					),
				),
			)
		);
		try {
			( new ApiClient( $http, self::TEST_KEY, 'https://x' ) )->create_invoice( array() );
			self::fail( 'Expected ApiError' );
		} catch ( ApiError $error ) {
			self::assertSame( array( 422, 'validation_error', 'Invalid invoice' ), array( $error->status, $error->error_code, $error->getMessage() ) );
			self::assertSame(
				array(
					array(
						'field'   => 'tax_lines.0',
						'message' => 'bad tax',
					),
				),
				$error->errors
			);
			self::assertFalse( $error->is_retryable() );
		}
	}

	/**
	 * @return iterable<string, array{int, string, bool}>
	 */
	public static function statuses(): iterable {
		yield 'gateway' => array( 502, '<html>Bad gateway</html>', true );
		yield 'rate limited' => array( 429, '{"code":"rate_limited","title":"Slow down"}', true );
		yield 'unauthenticated' => array( 401, '{"code":"unauthenticated","title":"Bad key"}', false );
		yield 'ok but not json' => array( 200, 'not json', false );
	}

	/**
	 * @dataProvider statuses
	 */
	public function test_retryable_or_not( int $status, string $body, bool $retryable ): void {
		$http            = new FakeHttp();
		$http->answers[] = new HttpResponse( $status, array(), $body );
		try {
			( new ApiClient( $http, self::TEST_KEY, 'https://x' ) )->me();
			self::fail( 'Expected ApiError' );
		} catch ( ApiError $error ) {
			self::assertSame( $retryable, $error->is_retryable() );
		}
	}

	public function test_no_answer_is_retryable(): void {
		$this->expectExceptionObject( new ApiError( 0, 'network', 'Huella Cloud did not answer: timed out' ) );
		( new ApiClient( ( new FakeHttp() )->no_answer(), self::TEST_KEY, 'https://x' ) )->me();
	}
}
