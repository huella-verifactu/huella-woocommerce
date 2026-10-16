<?php
/**
 * End to end in a real WordPress (wp-env): order -> WP Overnight invoice -> background job ->
 * Huella (fake API) -> order meta and notes. Run with:
 *
 *     npx wp-env run cli wp eval-file wp-content/plugins/huella-verifactu/tests/e2e/register-order.php
 *
 * Exits with an error on the first failed check.
 *
 * @package Huella\Verifactu
 */

// No strict_types: wp eval-file runs this through eval().

use Huella\Verifactu\Woo\ActionSchedulerJobs;
use Huella\Verifactu\Woo\Meta;
use Huella\Verifactu\Woo\Options;

$check = static function ( bool $ok, string $what ): void {
	if ( ! $ok ) {
		WP_CLI::error( "FAILED: {$what}" );
	}
	WP_CLI::log( "ok   {$what}" );
};

// --- A Spanish shop with 21 % IVA, prices including tax ------------------------------------------
update_option( 'woocommerce_default_country', 'ES:M' );
update_option( 'woocommerce_currency', 'EUR' );
update_option( 'woocommerce_calc_taxes', 'yes' );
update_option( 'woocommerce_prices_include_tax', 'yes' );
update_option( 'woocommerce_tax_based_on', 'base' );
// WP Overnight stores its defaults on the first admin page load, which a CLI-only site never
// makes: enable the invoice as a shop would.
update_option( 'wpo_wcpdf_documents_settings_invoice', array( 'enabled' => '1' ) );
if ( ! WC_Tax::find_rates( array( 'country' => 'ES', 'tax_class' => '' ) ) ) {
	WC_Tax::_insert_tax_rate(
		array(
			'tax_rate_country'  => 'ES',
			'tax_rate'          => '21.0000',
			'tax_rate_name'     => 'IVA',
			'tax_rate_priority' => 1,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 0,
			'tax_rate_class'    => '',
		)
	);
}
update_option(
	Options::OPTION,
	array(
		'api_key'   => 'hk_test_' . str_repeat( 'e', 43 ),
		'issuer_id' => 'iss_' . str_repeat( '0', 32 ),
	)
);
delete_option( 'huella_fake_api_requests' );

$product = new WC_Product_Simple();
$product->set_name( 'Camiseta' );
$product->set_regular_price( '100' );
$product->save();

$new_order = static function ( WC_Product $product ): WC_Order {
	$order = wc_create_order();
	$order->set_billing_first_name( 'Ana' );
	$order->set_billing_last_name( 'García' );
	$order->set_billing_country( 'ES' );
	$order->add_product( $product, 1 );
	$order->calculate_totals();
	$order->set_status( 'processing' );
	$order->save();
	return $order;
};

// Runs our due actions through Action Scheduler itself (claims, hooks, logs), as WP-Cron would.
$run_jobs = static function (): void {
	$due = as_get_scheduled_actions(
		array(
			'hook'         => ActionSchedulerJobs::SEND,
			'group'        => ActionSchedulerJobs::GROUP,
			'status'       => ActionScheduler_Store::STATUS_PENDING,
			'date'         => as_get_datetime_object(),
			'date_compare' => '<=',
			'per_page'     => 50,
		),
		'ids'
	);
	foreach ( $due as $action_id ) {
		ActionScheduler_QueueRunner::instance()->process_action( (int) $action_id, 'huella-e2e' );
	}
};

// --- 1. An invoice is registered in the background ----------------------------------------------
$order   = $new_order( $product );
$invoice = wcpdf_get_document( 'invoice', $order, true );
$check( $invoice && $invoice->exists(), 'WP Overnight created the invoice' );
$order = wc_get_order( $order->get_id() );
$check( 'pending' === $order->get_meta( Meta::STATUS ), 'the invoice hook queued the send (status pending)' );
$check( false !== as_has_scheduled_action( ActionSchedulerJobs::SEND, array( $order->get_id() ), ActionSchedulerJobs::GROUP ), 'an Action Scheduler job exists' );

$run_jobs();
$order    = wc_get_order( $order->get_id() );
$requests = get_option( 'huella_fake_api_requests', array() );
$sent     = end( $requests );
$number   = $invoice->get_number()->get_formatted();
$check( 0 === strpos( (string) $order->get_meta( Meta::RECORD_ID ), 'rec_' ), 'the record ID is stored' );
$check( 'queued' === $order->get_meta( Meta::STATUS ), 'the status is the API record status' );
$check( 64 === strlen( (string) $order->get_meta( Meta::HUELLA ) ), 'the huella is stored' );
$check( $number === $order->get_meta( Meta::INVOICE_NUMBER ), 'the registered invoice number is stored' );
$check( 'POST' === $sent['method'] && '/v1/invoices' === $sent['path'], 'POST /v1/invoices was called' );
$check( 0 === strpos( (string) $sent['auth'], 'Bearer hk_test_' ), 'with the API key' );
$check( $number === $sent['body']['series_number'], 'series_number is the WP Overnight number' );
$check( current_time( 'Y-m-d' ) === $sent['body']['issue_date'], 'issue_date is the invoice date' );
$check( 'F2' === $sent['body']['type'] && ! isset( $sent['body']['recipient'] ), 'a simplified invoice (no NIF)' );
$check(
	array( array( 'rate' => '21.00', 'base' => '82.64', 'tax' => '17.36' ) ) === $sent['body']['tax_lines'],
	'tax lines 21.00 / 82.64 / 17.36'
);
$check( '100.00' === $sent['body']['total'] && 'wc_order_' . $order->get_id() === $sent['body']['external_ref'], 'total and external_ref' );
$notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
$check( (bool) array_filter( $notes, static function ( $note ) use ( $number ) { return false !== strpos( $note->content, "factura {$number} registrada" ); } ), 'an order note says it is registered' );

// The hook fires again on later saves: nothing is sent twice.
$invoice->save();
$run_jobs();
$check( count( get_option( 'huella_fake_api_requests', array() ) ) === count( $requests ), 'saving the invoice again sends nothing' );

// --- 2. No answer: retried later ---------------------------------------------------------------
update_option( 'huella_fake_api_fail_next', 503 );
$second = $new_order( $product );
wcpdf_get_document( 'invoice', $second, true );
$run_jobs();
$second = wc_get_order( $second->get_id() );
$check( 'retrying' === $second->get_meta( Meta::STATUS ) && '1' === (string) $second->get_meta( Meta::FAILURES ), 'a 503 leaves the order retrying with 1 failure' );
$next = as_next_scheduled_action( ActionSchedulerJobs::SEND, array( $second->get_id() ), ActionSchedulerJobs::GROUP );
$check( is_int( $next ) && $next > time() + 30, 'the retry is scheduled about a minute later' );

WP_CLI::success( 'end-to-end checks passed' );
