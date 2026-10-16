<?php
/**
 * The HTTP the API client needs. WpHttp sends through WordPress; tests pass a fake.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Api;

interface Http {

	/**
	 * @param array<string, string> $headers Request headers.
	 * @throws ApiError With status 0 when no answer arrived (network, timeout, DNS).
	 */
	public function request( string $method, string $url, array $headers, ?string $body, int $timeout ): HttpResponse;
}
