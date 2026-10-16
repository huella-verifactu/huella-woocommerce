<?php
/**
 * The "Test connection" panel on the settings page (SPEC 6.4): environment badge, account,
 * issuers, and the warnings that matter before the first order.
 *
 * Answers are cached for 5 minutes, so opening the settings page does not call the API each time.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Admin;

use Huella\Verifactu\Api\ApiError;
use Huella\Verifactu\Woo\Options;

final class Connection {

	private const CACHE   = 'huella_verifactu_connection';
	private const SECONDS = 300;

	/**
	 * The account and issuers as Huella answered, or the error.
	 *
	 * @return array{ok: bool, account?: string, environment?: string, issuers?: list<array{id: string, nif: string, legal_name: string, simplified_limit: string}>, error?: string}
	 */
	public static function status(): array {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) && true === ( $cached['ok'] ?? null ) && is_array( $cached['issuers'] ?? null ) ) {
			return array(
				'ok'          => true,
				'account'     => is_string( $cached['account'] ?? null ) ? $cached['account'] : '',
				'environment' => 'live' === ( $cached['environment'] ?? '' ) ? 'live' : 'test',
				'issuers'     => self::issuer_list( $cached['issuers'] ),
			);
		}
		$api = Options::api();
		if ( null === $api ) {
			return array(
				'ok'    => false,
				'error' => '' === Options::api_key()
					? __( 'Añade tu clave de API de Huella para empezar.', 'huella-verifactu' )
					: __( 'La clave de API no tiene el formato de Huella (hk_test_… o hk_live_…), o la URL del servicio no es https.', 'huella-verifactu' ),
			);
		}
		try {
			$me      = $api->me();
			$issuers = self::issuer_list( $api->issuers() );
			$account = is_array( $me['account'] ?? null ) ? (string) ( $me['account']['name'] ?? '' ) : '';
			$status  = array(
				'ok'          => true,
				'account'     => $account,
				'environment' => $api->environment(),
				'issuers'     => $issuers,
			);
		} catch ( ApiError $error ) {
			$status = array(
				'ok'    => false,
				'error' => sprintf(
					/* translators: %s: error from Huella */
					__( 'No se puede conectar con Huella: %s', 'huella-verifactu' ),
					$error->getMessage()
				),
			);
		}
		set_transient( self::CACHE, $status, self::SECONDS );
		return $status;
	}

	/**
	 * Issuers as plain strings, whatever the source (API answer or cache).
	 *
	 * @param array<mixed> $raw Issuer objects.
	 * @return list<array{id: string, nif: string, legal_name: string, simplified_limit: string}>
	 */
	private static function issuer_list( array $raw ): array {
		$issuers = array();
		foreach ( $raw as $issuer ) {
			if ( ! is_array( $issuer ) || ! is_string( $issuer['id'] ?? null ) ) {
				continue;
			}
			$text      = static function ( string $key, string $fallback = '' ) use ( $issuer ): string {
				return is_scalar( $issuer[ $key ] ?? null ) ? (string) $issuer[ $key ] : $fallback;
			};
			$issuers[] = array(
				'id'               => $issuer['id'],
				'nif'              => $text( 'nif' ),
				'legal_name'       => $text( 'legal_name' ),
				'simplified_limit' => $text( 'simplified_limit', '400.00' ),
			);
		}
		return $issuers;
	}

	/**
	 * @return list<array{id: string, nif: string, legal_name: string, simplified_limit: string}>
	 */
	public static function issuers(): array {
		return self::status()['issuers'] ?? array();
	}

	public static function forget(): void {
		delete_transient( self::CACHE );
	}

	public static function render(): void {
		$status = self::status();
		echo '<div class="huella-connection" style="margin:1em 0;padding:12px 16px;background:#fff;border:1px solid #c3c4c7;">';
		if ( $status['ok'] ) {
			$live  = 'live' === ( $status['environment'] ?? '' );
			$badge = $live
				? '<span style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:3px;font-weight:600;">PRODUCCIÓN</span>'
				: '<span style="background:#dba617;color:#1d2327;padding:2px 8px;border-radius:3px;font-weight:600;">PRUEBAS</span>';
			printf(
				'<p>%s %s</p>',
				$badge, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup above
				esc_html(
					sprintf(
						/* translators: 1: account name, 2: number of issuers */
						__( 'Conectado a Huella: cuenta «%1$s», %2$d emisor(es).', 'huella-verifactu' ),
						$status['account'] ?? '',
						count( $status['issuers'] ?? array() )
					)
				)
			);
		} else {
			printf( '<p style="color:#b32d2e;">%s</p>', esc_html( $status['error'] ?? '' ) );
		}
		foreach ( Checks::warnings() as $warning ) {
			printf( '<p style="color:#996800;">&#9888; %s</p>', esc_html( $warning ) );
		}
		echo '</div>';
	}
}
