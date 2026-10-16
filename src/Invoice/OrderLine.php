<?php
/**
 * One line of an order: a product, the shipping or a fee, after discounts.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Invoice;

final class OrderLine {

	/** @var string What the admin sees in an error ("Camiseta azul", "Envío"). */
	public $label;
	/** @var string Net amount after discounts, without tax (WC_Order_Item::get_total()). */
	public $net;
	/** @var array<array-key, string> Tax percent (21, "21" or "21.0000") => tax amount of this line. */
	public $taxes;

	/**
	 * @param array<array-key, string> $taxes Tax percent => tax amount (PHP turns "21" keys into ints).
	 */
	public function __construct( string $label, string $net, array $taxes ) {
		$this->label = $label;
		$this->net   = $net;
		$this->taxes = $taxes;
	}
}
