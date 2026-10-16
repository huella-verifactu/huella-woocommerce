<?php
/**
 * The order notes for record states reported by Huella Cloud.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Tests\Unit\Sending;

use Huella\Verifactu\Sending\StatusNote;
use PHPUnit\Framework\TestCase;

final class StatusNoteTest extends TestCase {

	public function test_accepted(): void {
		self::assertStringContainsString( 'ha aceptado', (string) StatusNote::for_state( 'accepted', array() ) );
	}

	public function test_rejected_carries_the_aeat_code_and_message(): void {
		$note = (string) StatusNote::for_state(
			'rejected',
			array(
				'code'    => '1104',
				'message' => 'El valor del campo NumSerieFactura es incorrecto.',
			)
		);
		self::assertStringContainsString( 'rechazado', $note );
		self::assertStringContainsString( 'código 1104: El valor del campo NumSerieFactura es incorrecto.', $note );
	}

	public function test_accepted_with_errors_without_detail(): void {
		self::assertStringContainsString( 'sin detalle', (string) StatusNote::for_state( 'accepted_with_errors', array( 'code' => null ) ) );
	}

	public function test_no_note_while_on_its_way(): void {
		self::assertNull( StatusNote::for_state( 'queued', array() ) );
		self::assertNull( StatusNote::for_state( 'sending', array() ) );
	}

	public function test_long_messages_are_cut(): void {
		$note = (string) StatusNote::for_state( 'rejected', array( 'message' => str_repeat( 'x', 2000 ) ) );
		self::assertLessThan( 500, strlen( $note ) );
	}
}
