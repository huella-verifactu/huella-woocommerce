<?php
/**
 * Contract run: this plugin against a real Huella Cloud (not the fake API). Manual, not in CI;
 * see tests/contract/README.md. Exits with an error on the first failed check.
 *
 * Expects HUELLA_API_URL to point at the cloud, and the plugin settings to hold a test key and
 * the issuer ID (the runner script sets both).
 *
 * @package Huella\Verifactu
 */

// No strict_types: wp eval-file runs this through eval().

defined( 'ABSPATH' ) || exit;

use Huella\Verifactu\Admin\Connection;
use Huella\Verifactu\Qr;
use Huella\Verifactu\Woo\ActionSchedulerJobs;
use Huella\Verifactu\Woo\Meta;
use Huella\Verifactu\Woo\Options;

$check = static function ( bool $ok, string $what ): void {
	if ( ! $ok ) {
		WP_CLI::error( "FAILED: {$what}" );
	}
	WP_CLI::log( "ok   {$what}" );
};

$check( 'https://huella.fake.test' !== Options::api_url(), 'HUELLA_API_URL points at a real cloud: ' . Options::api_url() );

// The same small Spanish shop as the e2e run (idempotent).
update_option( 'woocommerce_default_country', 'ES:M' );
update_option( 'woocommerce_currency', 'EUR' );
update_option( 'woocommerce_calc_taxes', 'yes' );
update_option( 'woocommerce_prices_include_tax', 'yes' );
update_option( 'wpo_wcpdf_documents_settings_invoice', array( 'enabled' => '1' ) );
if ( ! WC_Tax::find_rates( array( 'country' => 'ES', 'tax_class' => '' ) ) ) {
	WC_Tax::_insert_tax_rate( array( 'tax_rate_country' => 'ES', 'tax_rate' => '21.0000', 'tax_rate_name' => 'IVA', 'tax_rate_priority' => 1, 'tax_rate_shipping' => 1, 'tax_rate_order' => 0, 'tax_rate_class' => '' ) );
}

// 1. The settings side talks to the real API.
Connection::forget();
$status = Connection::status();
$check( true === $status['ok'], 'GET /v1/me answers: account ' . ( $status['account'] ?? '?' ) );
$check( 'test' === ( $status['environment'] ?? '' ), 'the test key means the test environment' );
$issuer_id = Options::get( 'issuer_id' );
$ids       = array_column( Connection::issuers(), 'id' );
$check( in_array( $issuer_id, $ids, true ), 'GET /v1/issuers lists the configured issuer' );
Options::remember_issuer( Connection::issuers() );
$check( '' !== Options::get( 'issuer_nif' ), 'the issuer NIF is kept for the QR: ' . Options::get( 'issuer_nif' ) );

// 2. A real order, a real WP Overnight invoice, the real API.
$product = new WC_Product_Simple();
$product->set_name( 'Camiseta contrato' );
$product->set_regular_price( '24.95' );
$product->save();
$order = wc_create_order();
$order->set_billing_first_name( 'Ana' );
$order->set_billing_last_name( 'García' );
$order->set_billing_country( 'ES' );
$order->add_product( $product, 3 );
$order->calculate_totals();
$order->set_status( 'processing' );
$order->save();
$invoice = wcpdf_get_document( 'invoice', $order, true );
$check( $invoice && $invoice->exists(), 'WP Overnight created invoice ' . $invoice->get_number()->get_formatted() );

$run = static function ( int $order_id ): void {
	$due = as_get_scheduled_actions( array( 'hook' => ActionSchedulerJobs::SEND, 'args' => array( $order_id ), 'status' => ActionScheduler_Store::STATUS_PENDING ), 'ids' );
	foreach ( $due as $action_id ) {
		ActionScheduler_QueueRunner::instance()->process_action( (int) $action_id, 'huella-contract' );
	}
};
$run( $order->get_id() );
$order = wc_get_order( $order->get_id() );
$check( 0 === strpos( (string) $order->get_meta( Meta::RECORD_ID ), 'rec_' ), 'POST /v1/invoices accepted the plugin payload: ' . $order->get_meta( Meta::RECORD_ID ) . ' / ' . $order->get_meta( Meta::LAST_ERROR ) );
$check( 'queued' === $order->get_meta( Meta::STATUS ), 'the record is queued for the worker' );

// 3. Both sides agree on the record and on the QR (D7).
$record = Options::api()->invoice( (string) $order->get_meta( Meta::RECORD_ID ) );
$check( $record['huella'] === $order->get_meta( Meta::HUELLA ), 'the huella stored on the order is the cloud\'s' );
$check( $record['series_number'] === $invoice->get_number()->get_formatted(), 'the cloud registered the WP Overnight number' );
$check( Huella\Verifactu\Money::text( (string) $order->get_total( 'edit' ) ) === $record['total'], 'the cloud registered the order total' );
$plugin_qr = Qr::url( Options::get( 'issuer_nif' ), $invoice->get_number()->get_formatted(), $invoice->get_date()->date( 'Y-m-d' ), Huella\Verifactu\Money::text( (string) $order->get_total( 'edit' ) ), 'test' );
$check( $plugin_qr === $record['qr_url'], "the QR printed in the PDF is the cloud's QR URL\n     plugin: {$plugin_qr}\n     cloud:  {$record['qr_url']}" );

// 4. Sending again is a replay, never a second registration.
$first_id = (string) $order->get_meta( Meta::RECORD_ID );
$order->delete_meta_data( Meta::RECORD_ID );
$order->update_meta_data( Meta::STATUS, 'pending' );
$order->save();
( new ActionSchedulerJobs() )->send_soon( $order->get_id() );
$run( $order->get_id() );
$check( $first_id === wc_get_order( $order->get_id() )->get_meta( Meta::RECORD_ID ), 'a repeated send returns the same record (idempotent)' );

WP_CLI::success( 'the plugin and Huella Cloud agree' );
