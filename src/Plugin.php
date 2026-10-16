<?php
/**
 * Wires the plugin into WordPress. Features register themselves here as they are added.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu;

final class Plugin {

	/**
	 * Plugins this one cannot work without: WooCommerce and WP Overnight's invoices (SPEC 6.1).
	 */
	private const REQUIRED = array(
		'WooCommerce' => 'WooCommerce',
		'WPO_WCPDF'   => 'PDF Invoices & Packing Slips for WooCommerce', // Its main file, 5.x.
	);

	public static function boot( string $plugin_file ): void {
		add_action(
			'before_woocommerce_init',
			static function () use ( $plugin_file ): void {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $plugin_file, true );
				}
			}
		);
		add_action( 'plugins_loaded', array( self::class, 'start' ) );
	}

	public static function start(): void {
		$missing = self::missing_dependencies();
		if ( $missing ) {
			add_action(
				'admin_notices',
				static function () use ( $missing ): void {
					self::missing_notice( $missing );
				}
			);
			return;
		}
		// Features (settings, sending, QR, admin) are registered here in the next steps.
	}

	/**
	 * Names of the required plugins that are not active.
	 *
	 * @return list<string>
	 */
	public static function missing_dependencies(): array {
		$missing = array();
		foreach ( self::REQUIRED as $class => $name ) {
			if ( ! class_exists( $class ) ) {
				$missing[] = $name;
			}
		}
		return $missing;
	}

	/**
	 * @param list<string> $missing Plugin names.
	 */
	private static function missing_notice( array $missing ): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: comma-separated plugin names */
					__( 'Huella – Verifactu necesita estos plugins activos: %s. Mientras falten, no se registra ninguna factura.', 'huella-verifactu' ),
					implode( ', ', $missing )
				)
			)
		);
	}
}
