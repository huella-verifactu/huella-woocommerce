<?php
/**
 * What the Sender reads from and writes to one order. WcHuellaOrder implements it on a
 * WC_Order (HPOS-safe: only WC_Order methods); tests use an in-memory fake.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Sending;

use Huella\Verifactu\Invoice\OrderSnapshot;

interface HuellaOrder {

	public function id(): int;

	/**
	 * The order as the mapper needs it; null while it has no WP Overnight invoice.
	 */
	public function snapshot(): ?OrderSnapshot;

	/**
	 * The Huella record ID once registered, else null.
	 */
	public function record_id(): ?string;

	/**
	 * How many tries failed so far (for RetryPolicy).
	 */
	public function failures(): int;

	/**
	 * Registered: store the record and what was registered (number, date) for later checks.
	 *
	 * @param array<string, mixed> $record The record from the API.
	 * @param list<string>         $notes  Mapper notes, added to the order.
	 */
	public function registered( array $record, OrderSnapshot $snapshot, array $notes ): void;

	/**
	 * Not registered. ``$status`` is one of the Status constants; the message goes to the order.
	 */
	public function not_registered( string $status, string $code, string $message, int $failures ): void;
}
