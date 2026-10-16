<?php
/**
 * The buyer, as far as the invoice type is concerned.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Invoice;

final class Customer {

	/** @var string Billing company, or first and last name. */
	public $name;
	/** @var string NIF or EU VAT number as typed at checkout; "" when none. */
	public $tax_id;
	/** @var string Billing country, ISO 3166-1 alpha-2. */
	public $country;

	public function __construct( string $name, string $tax_id, string $country ) {
		$this->name    = $name;
		$this->tax_id  = $tax_id;
		$this->country = $country;
	}
}
