<?php
/**
 * WooCommerce → Ajustes → Verifactu (SPEC 6.4). Built on WooCommerce's settings API, so the
 * fields look and save like every other WooCommerce setting.
 *
 * Loaded only through the woocommerce_get_settings_pages filter, when WC_Settings_Page exists.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Admin;

use Huella\Verifactu\Woo\Options;

final class SettingsPage extends \WC_Settings_Page {

	public function __construct() {
		$this->id    = 'huella_verifactu';
		$this->label = 'Verifactu';
		parent::__construct();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_default_section() {
		$option   = Options::OPTION;
		$constant = defined( 'HUELLA_API_KEY' );
		$issuers  = array( '' => __( '— Elige el emisor —', 'huella-verifactu' ) );
		foreach ( Connection::issuers() as $issuer ) {
			$issuers[ $issuer['id'] ] = $issuer['nif'] . ' – ' . $issuer['legal_name'];
		}
		return array(
			array(
				'type'  => 'title',
				'id'    => 'huella_verifactu_connection',
				'title' => __( 'Conexión con Huella', 'huella-verifactu' ),
				'desc'  => __( 'La clave de API decide el entorno: hk_test_ registra en el entorno de pruebas de la AEAT, hk_live_ en producción.', 'huella-verifactu' ),
			),
			array(
				'type'              => 'password',
				'id'                => "{$option}[api_key]",
				'title'             => __( 'Clave de API', 'huella-verifactu' ),
				'desc'              => $constant
					? __( 'Definida en wp-config.php (HUELLA_API_KEY). Este campo no se usa.', 'huella-verifactu' )
					: __( 'Recomendado: defínela en wp-config.php con define( \'HUELLA_API_KEY\', \'hk_…\' ); así no se guarda en la base de datos.', 'huella-verifactu' ),
				'custom_attributes' => $constant ? array( 'disabled' => 'disabled' ) : array( 'autocomplete' => 'off' ),
			),
			array(
				'type'    => 'select',
				'id'      => "{$option}[issuer_id]",
				'title'   => __( 'Emisor', 'huella-verifactu' ),
				'desc'    => __( 'La empresa o autónomo que emite las facturas (NIF del obligado tributario). Guarda la clave primero para cargar la lista.', 'huella-verifactu' ),
				'options' => $issuers,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'huella_verifactu_connection',
			),
			array(
				'type'  => 'title',
				'id'    => 'huella_verifactu_invoices',
				'title' => __( 'Facturas', 'huella-verifactu' ),
			),
			array(
				'type'    => 'text',
				'id'      => "{$option}[description_template]",
				'title'   => __( 'Descripción de la operación', 'huella-verifactu' ),
				'desc'    => __( 'Se envía a la AEAT con cada factura. Puedes usar {order_number} y {site_name}.', 'huella-verifactu' ),
				'default' => Options::DEFAULT_DESCRIPTION,
			),
			array(
				'type'    => 'select',
				'id'      => "{$option}[nif_mode]",
				'title'   => __( 'NIF del cliente', 'huella-verifactu' ),
				'desc'    => __( 'Con un NIF válido la factura es completa (F1); sin él, simplificada (F2).', 'huella-verifactu' ),
				'default' => 'field',
				'options' => array(
					'field' => __( 'Añadir un campo NIF/CIF opcional en el checkout', 'huella-verifactu' ),
					'meta'  => __( 'Leerlo de un campo que ya tengo (clave meta del pedido)', 'huella-verifactu' ),
				),
			),
			array(
				'type'    => 'text',
				'id'      => "{$option}[tax_id_meta_key]",
				'title'   => __( 'Clave meta del NIF', 'huella-verifactu' ),
				'desc'    => __( 'Solo si eliges «Leerlo de un campo que ya tengo». Por ejemplo _billing_nif.', 'huella-verifactu' ),
				'default' => Options::DEFAULT_TAX_ID_META_KEY,
			),
			array(
				'type'    => 'select',
				'id'      => "{$option}[qr_position]",
				'title'   => __( 'Posición del QR en el PDF', 'huella-verifactu' ),
				'default' => 'after_document_label',
				'options' => array(
					'after_document_label'  => __( 'Debajo del título «Factura»', 'huella-verifactu' ),
					'after_order_details'   => __( 'Después de las líneas del pedido', 'huella-verifactu' ),
					'before_document_notes' => __( 'Antes de las notas', 'huella-verifactu' ),
				),
			),
			array(
				'type'    => 'email',
				'id'      => "{$option}[alert_email]",
				'title'   => __( 'Email de avisos', 'huella-verifactu' ),
				'desc'    => __( 'Para registros rechazados y caídas del servicio.', 'huella-verifactu' ),
				'default' => (string) get_option( 'admin_email' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'huella_verifactu_invoices',
			),
		);
	}

	/**
	 * @return void
	 */
	public function output() {
		Connection::render();
		parent::output();
	}

	/**
	 * @return void
	 */
	public function save() {
		parent::save();
		Connection::forget();
		Options::remember_issuer_limit( Connection::issuers() );
	}
}
