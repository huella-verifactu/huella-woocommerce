<?php
/**
 * An HTTP answer: status, headers (lowercase names) and body.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Api;

final class HttpResponse {

	/** @var int */
	public $status;
	/** @var array<string, string> Lowercase header names. */
	public $headers;
	/** @var string */
	public $body;

	/**
	 * @param array<string, string> $headers Lowercase header names.
	 */
	public function __construct( int $status, array $headers, string $body ) {
		$this->status  = $status;
		$this->headers = $headers;
		$this->body    = $body;
	}
}
