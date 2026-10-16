<?php
/**
 * A failed call to Huella Cloud. ``retryable`` decides between "try again later" and "a person
 * must fix something first" (SPEC 6.6).
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Api;

use RuntimeException;

final class ApiError extends RuntimeException {

	/** @var int HTTP status; 0 when no answer arrived. */
	public $status;
	/** @var string The API's problem code ("validation_error"), or "network" / "unexpected". */
	public $error_code;
	/** @var list<array{field: string, message: string}> Field errors of a 422. */
	public $errors;

	/**
	 * @param list<array{field: string, message: string}> $errors Field errors.
	 */
	public function __construct( int $status, string $error_code, string $message, array $errors = array() ) {
		parent::__construct( $message );
		$this->status     = $status;
		$this->error_code = $error_code;
		$this->errors     = $errors;
	}

	/**
	 * No answer, rate limited, or the service failing: the same request may work later.
	 * Any other 4xx means the request itself is wrong; sending it again would not help.
	 */
	public function is_retryable(): bool {
		return 0 === $this->status || 429 === $this->status || $this->status >= 500;
	}
}
