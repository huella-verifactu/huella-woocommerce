<?php
/**
 * Huella Cloud API v1 (SPEC 5.4): the calls the plugin makes.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Api;

use InvalidArgumentException;

final class ApiClient {

	public const TIMEOUT = 10; // Seconds. Always off the customer's request (SPEC 6.6).

	/** @var Http */
	private $http;
	/** @var string */
	private $api_key;
	/** @var string */
	private $base_url;

	/**
	 * @throws InvalidArgumentException When the key is not a Huella key.
	 */
	public function __construct( Http $http, string $api_key, string $base_url ) {
		if ( ! preg_match( '/^hk_(test|live)_[A-Za-z0-9_-]{20,}$/', $api_key ) ) {
			throw new InvalidArgumentException( 'Not a Huella API key (hk_test_... or hk_live_...)' );
		}
		$this->http     = $http;
		$this->api_key  = $api_key;
		$this->base_url = rtrim( $base_url, '/' );
	}

	/**
	 * "test" (AEAT pre-production) or "live": the key decides (D11).
	 */
	public function environment(): string {
		return 0 === strpos( $this->api_key, 'hk_live_' ) ? 'live' : 'test';
	}

	/**
	 * @return array<string, mixed> Account and key (GET /v1/me).
	 */
	public function me(): array {
		return $this->call( 'GET', '/v1/me' );
	}

	/**
	 * @return list<array<string, mixed>> Issuers the key may use.
	 */
	public function issuers(): array {
		$data = $this->call( 'GET', '/v1/issuers' )['data'] ?? array();
		return is_array( $data ) ? array_values( $data ) : array();
	}

	/**
	 * Register an invoice. Safe to repeat: the same number with the same data answers the same
	 * record (200, "Idempotent-Replay"), never a second registration (SPEC 5.4).
	 *
	 * @param array<string, mixed> $payload Body of POST /v1/invoices (PayloadMapper).
	 * @return array<string, mixed> The record.
	 */
	public function create_invoice( array $payload ): array {
		return $this->call( 'POST', '/v1/invoices', $payload );
	}

	/**
	 * @return array<string, mixed> The record.
	 */
	public function invoice( string $record_id ): array {
		return $this->call( 'GET', '/v1/invoices/' . rawurlencode( $record_id ) );
	}

	/**
	 * @param array<string, mixed>|null $payload JSON body.
	 * @return array<string, mixed> Decoded JSON answer.
	 * @throws ApiError On any answer other than 2xx with a JSON object.
	 */
	private function call( string $method, string $path, ?array $payload = null ): array {
		$headers = array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Accept'        => 'application/json',
			'User-Agent'    => 'huella-verifactu/' . ( defined( 'HUELLA_VERIFACTU_VERSION' ) ? HUELLA_VERIFACTU_VERSION : 'dev' ),
		);
		$body    = null;
		if ( null !== $payload ) {
			$headers['Content-Type'] = 'application/json';
			$body                    = (string) json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		$response = $this->http->request( $method, $this->base_url . $path, $headers, $body, self::TIMEOUT );
		$data     = json_decode( $response->body, true );
		if ( $response->status >= 200 && $response->status < 300 && is_array( $data ) ) {
			return $data;
		}
		throw self::error( $response->status, is_array( $data ) ? $data : array() );
	}

	/**
	 * An ApiError from a problem+json body (SPEC 5.4), or from the status alone.
	 *
	 * @param array<string, mixed> $problem Decoded body.
	 */
	private static function error( int $status, array $problem ): ApiError {
		$code   = is_string( $problem['code'] ?? null ) ? $problem['code'] : 'unexpected';
		$title  = is_string( $problem['title'] ?? null ) ? $problem['title'] : "Huella Cloud answered HTTP {$status}";
		$errors = array();
		foreach ( is_array( $problem['errors'] ?? null ) ? $problem['errors'] : array() as $error ) {
			if ( is_array( $error ) && is_string( $error['field'] ?? null ) && is_string( $error['message'] ?? null ) ) {
				$errors[] = array(
					'field'   => $error['field'],
					'message' => $error['message'],
				);
			}
		}
		return new ApiError( $status, $code, $title, $errors );
	}
}
