<?php
/**
 * Admin notices (SPEC 6.10): missing configuration, invoices disabled, sending paused, test mode. Shown only to
 * people who manage WooCommerce, and only on the dashboard, plugins and WooCommerce screens.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Admin;

use Huella\Verifactu\Woo\Options;
use Huella\Verifactu\Woo\Webhooks;

final class Notices {

	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! self::relevant_screen() ) {
			return;
		}
		$settings = admin_url( 'admin.php?page=wc-settings&tab=huella_verifactu' );
		$api      = Options::api();
		if ( null === $api || null === Options::mapper_settings() ) {
			self::notice(
				'warning',
				__( 'Huella – Verifactu aún no está configurado: añade la clave de API y elige el emisor. Mientras tanto no se registra ninguna factura.', 'huella-verifactu' ),
				$settings
			);
			return;
		}
		if ( ! Checks::invoices_enabled() ) {
			self::notice(
				'error',
				__( 'PDF Invoices & Packing Slips no tiene la factura activada: no se crea ninguna factura y no se registra nada en la AEAT.', 'huella-verifactu' ),
				admin_url( 'admin.php?page=wpo_wcpdf_options_page&tab=documents' )
			);
		}
		if ( null !== Webhooks::paused_since() ) {
			self::notice(
				'error',
				__( 'La AEAT ha rechazado el certificado del emisor y Huella ha pausado el envío de facturas. Sube un certificado válido en el panel de Huella: las facturas pendientes se enviarán solas.', 'huella-verifactu' ),
				rtrim( Options::api_url(), '/' ) . '/dashboard/issuers',
				__( 'Abrir el panel de Huella', 'huella-verifactu' )
			);
		}
		if ( 'test' === $api->environment() ) {
			self::notice(
				'warning',
				__( 'Verifactu en modo PRUEBAS: las facturas se registran en el entorno de pruebas de la AEAT y no tienen validez fiscal. Usa una clave hk_live_ cuando empieces a vender.', 'huella-verifactu' ),
				$settings
			);
		}
	}

	private static function relevant_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen ) {
			return false;
		}
		return in_array( $screen->id, array( 'dashboard', 'plugins', 'edit-shop_order', 'shop_order' ), true )
			|| false !== strpos( $screen->id, 'woocommerce' )
			|| false !== strpos( $screen->id, 'wc-orders' );
	}

	private static function notice( string $type, string $message, string $link, string $label = '' ): void {
		printf(
			'<div class="notice notice-%1$s"><p>%2$s <a href="%3$s">%4$s</a></p></div>',
			esc_attr( $type ),
			esc_html( $message ),
			esc_url( $link ),
			esc_html( '' === $label ? __( 'Abrir ajustes', 'huella-verifactu' ) : $label )
		);
	}
}
