<?php
/**
 * What the mapper needs from an order with a WP Overnight invoice, as plain values.
 *
 * Built from a WC_Order by OrderReader; built by hand in tests. Keeping WordPress out of the
 * mapper is what lets every rule of SPEC 6.5 be tested without a WordPress install.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Invoice;

final class OrderSnapshot {

	/** @var int */
	public $order_id;
	/** @var string */
	public $order_number;
	/** @var string NumSerieFactura: the formatted WP Overnight invoice number. */
	public $invoice_number;
	/** @var string Issue date, YYYY-MM-DD in the shop's time zone. */
	public $invoice_date;
	/** @var string Order total as WooCommerce stores it (what the customer paid). */
	public $total;
	/** @var list<OrderLine> Products, shipping and fees. */
	public $lines;
	/** @var Customer */
	public $customer;

	/**
	 * @param list<OrderLine> $lines Products, shipping and fees.
	 */
	public function __construct(
		int $order_id,
		string $order_number,
		string $invoice_number,
		string $invoice_date,
		string $total,
		array $lines,
		Customer $customer
	) {
		$this->order_id       = $order_id;
		$this->order_number   = $order_number;
		$this->invoice_number = $invoice_number;
		$this->invoice_date   = $invoice_date;
		$this->total          = $total;
		$this->lines          = $lines;
		$this->customer       = $customer;
	}
}
