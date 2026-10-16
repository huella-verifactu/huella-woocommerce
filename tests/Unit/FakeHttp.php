<?php
/**
 * Test double: HTTP answers queued in advance; every request is recorded.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit;

use Huella\Verifactu\Api\ApiError;
use Huella\Verifactu\Api\Http;
use Huella\Verifactu\Api\HttpResponse;

final class FakeHttp implements Http {

	/** @var list<HttpResponse|ApiError> */
	public $answers = array();
	/** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string, timeout: int}> */
	public $requests = array();

	/**
	 * @param array<string, mixed> $json Response body.
	 */
	public function answer( int $status, array $json ): self {
		$this->answers[] = new HttpResponse( $status, array( 'content-type' => 'application/json' ), (string) json_encode( $json ) );
		return $this;
	}

	public function no_answer(): self {
		$this->answers[] = new ApiError( 0, 'network', 'Huella Cloud did not answer: timed out' );
		return $this;
	}

	public function request( string $method, string $url, array $headers, ?string $body, int $timeout ): HttpResponse {
		$this->requests[] = compact( 'method', 'url', 'headers', 'body', 'timeout' );
		$answer           = array_shift( $this->answers );
		if ( null === $answer ) {
			throw new \LogicException( "Unexpected request: {$method} {$url}" );
		}
		if ( $answer instanceof ApiError ) {
			throw $answer;
		}
		return $answer;
	}
}
