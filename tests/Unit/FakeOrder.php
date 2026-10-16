<?php
/**
 * Test double: an order kept in memory.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit;

use Huella\Verifactu\Invoice\OrderSnapshot;
use Huella\Verifactu\Sending\HuellaOrder;

final class FakeOrder implements HuellaOrder {

	/** @var ?OrderSnapshot */
	public $snapshot;
	/** @var ?string */
	public $record_id = null;
	/** @var int */
	public $failures = 0;
	/** @var ?string */
	public $status = null;
	/** @var ?string */
	public $code = null;
	/** @var ?string */
	public $message = null;
	/** @var array<string, mixed>|null */
	public $record = null;
	/** @var list<string> */
	public $notes = array();

	public function __construct( ?OrderSnapshot $snapshot ) {
		$this->snapshot = $snapshot;
	}

	public function id(): int {
		return null === $this->snapshot ? 0 : $this->snapshot->order_id;
	}

	public function snapshot(): ?OrderSnapshot {
		return $this->snapshot;
	}

	public function record_id(): ?string {
		return $this->record_id;
	}

	public function failures(): int {
		return $this->failures;
	}

	public function registered( array $record, OrderSnapshot $snapshot, array $notes ): void {
		$this->record    = $record;
		$this->record_id = is_string( $record['id'] ?? null ) ? $record['id'] : null;
		$this->status    = is_string( $record['status'] ?? null ) ? $record['status'] : null;
		$this->notes     = $notes;
	}

	public function not_registered( string $status, string $code, string $message, int $failures ): void {
		$this->status   = $status;
		$this->code     = $code;
		$this->message  = $message;
		$this->failures = $failures;
	}
}
