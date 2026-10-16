<?php
/**
 * Plugin-side states of an order, stored in _huella_status next to the record states the API
 * returns (queued, sending, accepted, accepted_with_errors, rejected).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Sending;

final class Status {

	/** The invoice exists; a send is queued. */
	public const PENDING = 'pending';
	/** Huella Cloud could not be reached; a retry is scheduled. */
	public const RETRYING = 'retrying';
	/** Huella Cloud refused the request; a person must fix something, then "Reintentar". */
	public const ERROR = 'error';
	/** The order is outside what v1 registers (SPEC 1.2); nothing was sent. */
	public const UNSUPPORTED = 'unsupported';
	/** The plugin has no API key or issuer yet. */
	public const NOT_CONFIGURED = 'not_configured';
}
