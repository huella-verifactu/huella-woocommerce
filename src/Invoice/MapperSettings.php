<?php
/**
 * Shop settings the mapper needs (SPEC 6.4).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Invoice;

final class MapperSettings {

	/** @var string Huella issuer ID (iss_...). */
	public $issuer_id;
	/** @var string Highest total of a simplified invoice (F2), from the issuer: "400.00". */
	public $simplified_limit;
	/** @var string With {order_number} and {site_name}. */
	public $description_template;
	/** @var string */
	public $site_name;

	public function __construct( string $issuer_id, string $simplified_limit, string $description_template, string $site_name ) {
		$this->issuer_id            = $issuer_id;
		$this->simplified_limit     = $simplified_limit;
		$this->description_template = $description_template;
		$this->site_name            = $site_name;
	}
}
