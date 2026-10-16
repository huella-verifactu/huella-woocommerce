<?php
/**
 * An order the plugin must not register as it is (SPEC 1.2, 6.5). Nothing is sent; the admin
 * gets a notice built from the reason code, which stays stable for translations and tests.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Invoice;

use RuntimeException;

final class Unsupported extends RuntimeException {

	/** @var string */
	public $reason;

	public function __construct( string $reason, string $message ) {
		parent::__construct( $message );
		$this->reason = $reason;
	}
}
