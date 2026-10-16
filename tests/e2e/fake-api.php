<?php
/**
 * Plugin Name: Huella fake API (tests only)
 * Description: Answers for Huella Cloud through pre_http_request when HUELLA_API_URL is the
 *              fake host. Records every request in the option huella_fake_api_requests. Put
 *              an HTTP status in the option huella_fake_api_fail_next to fail the next call.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		$base = 'https://huella.fake.test';
		if ( ! defined( 'HUELLA_API_URL' ) || HUELLA_API_URL !== $base || 0 !== strpos( (string) $url, $base ) ) {
			return $preempt;
		}
		$path     = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		$method   = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
		$body     = isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null;
		$log      = get_option( 'huella_fake_api_requests', array() );
		$log[]    = array(
			'method' => $method,
			'path'   => $path,
			'body'   => $body,
			'auth'   => $args['headers']['Authorization'] ?? '',
		);
		update_option( 'huella_fake_api_requests', $log, false );

		$fail = (int) get_option( 'huella_fake_api_fail_next', 0 );
		if ( $fail > 0 ) {
			delete_option( 'huella_fake_api_fail_next' );
			return array(
				'headers'  => array(),
				'body'     => '<html>Service unavailable</html>',
				'response' => array( 'code' => $fail, 'message' => 'Fake failure' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		$json = array( 'code' => 'not_found', 'title' => 'Not found' );
		$code = 404;
		if ( 'GET' === $method && '/v1/me' === $path ) {
			$code = 200;
			$json = array(
				'account' => array( 'id' => 'acc_1', 'name' => 'Tienda Fake', 'plan' => 'trial', 'live_enabled' => false ),
				'key'     => array( 'prefix' => 'hk_test_eeee', 'environment' => 'test', 'issuer_ids' => null ),
			);
		}
		if ( 'GET' === $method && '/v1/issuers' === $path ) {
			$code = 200;
			$json = array(
				'object' => 'list',
				'data'   => array(
					array( 'id' => 'iss_' . str_repeat( '0', 32 ), 'nif' => 'B12345674', 'legal_name' => 'TIENDA FAKE SL', 'simplified_limit' => '400.00' ),
				),
			);
		}
		if ( 'POST' === $method && '/v1/invoices' === $path && is_array( $body ) ) {
			$code = 201;
			$json = array(
				'id'           => 'rec_' . md5( (string) $body['series_number'] ),
				'object'       => 'record',
				'kind'         => 'alta',
				'environment'  => 'test',
				'series_number'=> $body['series_number'],
				'status'       => 'queued',
				'huella'       => strtoupper( hash( 'sha256', (string) wp_json_encode( $body ) ) ),
				'qr_url'       => 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?fake=1',
			);
		}
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => (string) wp_json_encode( $json ),
			'response' => array( 'code' => $code, 'message' => 'Fake' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
