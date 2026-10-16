<?php
/**
 * The mapper's result: the API payload, plus notes for the order (rounding, ignored NIF).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Invoice;

final class Mapped {

	/** @var array<string, mixed> Body of POST /v1/invoices. */
	public $payload;
	/** @var list<string> Added to the order as notes. */
	public $notes;

	/**
	 * @param array<string, mixed> $payload Body of POST /v1/invoices.
	 * @param list<string>         $notes   Notes for the order.
	 */
	public function __construct( array $payload, array $notes ) {
		$this->payload = $payload;
		$this->notes   = $notes;
	}
}
