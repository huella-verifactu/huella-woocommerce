<?php
/**
 * Registers one order's invoice with Huella Cloud (SPEC 6.6). Runs in the background (Action
 * Scheduler), never inside the customer's request.
 *
 * The decision, in order:
 * 1. Already registered? Done (the job may run twice).
 * 2. No WP Overnight invoice yet? Nothing to do; the invoice hook queues us when it exists.
 * 3. Not configured (no API key or issuer)? Record it; the admin sees a notice.
 * 4. Map the order (PayloadMapper). Out of scope? Record why; never sent.
 * 5. Send. Refused (4xx)? Record the error; no retry, a person must look.
 *    No answer, 429 or 5xx? Schedule the next try (RetryPolicy); after 48 hours, stop and alert.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Sending;

use Huella\Verifactu\Api\ApiClient;
use Huella\Verifactu\Api\ApiError;
use Huella\Verifactu\Invoice\MapperSettings;
use Huella\Verifactu\Invoice\PayloadMapper;
use Huella\Verifactu\Invoice\Unsupported;

final class Sender {

	/** @var ApiClient|null Null when no API key is configured. */
	private $api;
	/** @var MapperSettings|null Null when no issuer is chosen. */
	private $settings;
	/** @var Scheduler */
	private $scheduler;
	/** @var PayloadMapper */
	private $mapper;

	public function __construct( ?ApiClient $api, ?MapperSettings $settings, Scheduler $scheduler, ?PayloadMapper $mapper = null ) {
		$this->api       = $api;
		$this->settings  = $settings;
		$this->scheduler = $scheduler;
		$this->mapper    = $mapper ?? new PayloadMapper();
	}

	public function send( HuellaOrder $order ): void {
		if ( null !== $order->record_id() ) {
			return;
		}
		$snapshot = $order->snapshot();
		if ( null === $snapshot ) {
			return;
		}
		if ( null === $this->api || null === $this->settings ) {
			$order->not_registered( Status::NOT_CONFIGURED, 'not_configured', 'Huella is not configured: add the API key and choose the issuer in WooCommerce → Ajustes → Verifactu', $order->failures() );
			return;
		}
		try {
			$mapped = $this->mapper->map( $snapshot, $this->settings );
		} catch ( Unsupported $unsupported ) {
			$order->not_registered( Status::UNSUPPORTED, $unsupported->reason, $unsupported->getMessage(), $order->failures() );
			return;
		}
		try {
			$record = $this->api->create_invoice( $mapped->payload );
		} catch ( ApiError $error ) {
			$this->failed( $order, $error );
			return;
		}
		$order->registered( $record, $snapshot, $mapped->notes );
	}

	private function failed( HuellaOrder $order, ApiError $error ): void {
		$message = $error->getMessage();
		foreach ( $error->errors as $field_error ) {
			$message .= " ({$field_error['field']}: {$field_error['message']})";
		}
		if ( ! $error->is_retryable() ) {
			$order->not_registered( Status::ERROR, $error->error_code, $message, $order->failures() );
			return;
		}
		$failures = $order->failures() + 1;
		$delay    = RetryPolicy::delay_after( $failures );
		if ( null === $delay ) {
			$order->not_registered( Status::ERROR, 'gave_up', "Huella Cloud could not be reached for 48 hours; last error: {$message}", $failures );
			return;
		}
		$order->not_registered( Status::RETRYING, $error->error_code, $message, $failures );
		$this->scheduler->send_later( $order->id(), $delay );
	}
}
