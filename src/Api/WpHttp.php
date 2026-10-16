<?php
/**
 * Http through wp_remote_request(), so proxies and TLS settings of the site apply.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Api;

final class WpHttp implements Http {

	public function request( string $method, string $url, array $headers, ?string $body, int $timeout ): HttpResponse {
		$args = array(
			'method'      => $method,
			'headers'     => $headers,
			'timeout'     => $timeout,
			'redirection' => 0,
		);
		if ( null !== $body ) {
			$args['body'] = $body;
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new ApiError( 0, 'network', 'Huella Cloud did not answer: ' . $response->get_error_message() );
		}
		$headers = array();
		foreach ( wp_remote_retrieve_headers( $response ) as $name => $value ) {
			$headers[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}
		return new HttpResponse( (int) wp_remote_retrieve_response_code( $response ), $headers, wp_remote_retrieve_body( $response ) );
	}
}
