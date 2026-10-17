<?php
/**
 * One webhook event from Huella Cloud (SPEC 5.8), read defensively. Pure: tested without
 * WordPress.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Webhook;

final class Event {

	/** Events the plugin subscribes to when it registers its URL. */
	public const SUBSCRIBED = array(
		'record.accepted',
		'record.accepted_with_errors',
		'record.rejected',
		'issuer.paused',
	);

	/** @var string */
	public $id;
	/** @var string */
	public $type;
	/** @var array<string, mixed> */
	public $data;

	/**
	 * @param array<string, mixed> $data
	 */
	private function __construct( string $id, string $type, array $data ) {
		$this->id   = $id;
		$this->type = $type;
		$this->data = $data;
	}

	/**
	 * The event in a request body, or null when the body is not one.
	 */
	public static function parse( string $body ): ?self {
		$json = json_decode( $body, true );
		if ( ! is_array( $json ) ) {
			return null;
		}
		$id   = $json['id'] ?? null;
		$type = $json['type'] ?? null;
		$data = $json['data'] ?? null;
		if ( ! is_string( $id ) || ! preg_match( '/^evt_[0-9a-f]{32}$/', $id ) || ! is_string( $type ) || ! is_array( $data ) ) {
			return null;
		}
		return new self( $id, $type, $data );
	}

	public function is_record(): bool {
		return 0 === strpos( $this->type, 'record.' );
	}

	/**
	 * The WooCommerce order a record event is about, from its external_ref "wc_order_{id}".
	 */
	public function order_id(): ?int {
		$ref = $this->data['external_ref'] ?? null;
		if ( ! $this->is_record() || ! is_string( $ref ) || ! preg_match( '/^wc_order_([1-9][0-9]{0,18})$/', $ref, $match ) ) {
			return null;
		}
		return (int) $match[1];
	}

	/**
	 * The object's own ID (rec_… for a record, iss_… for an issuer), "" when missing or not
	 * shaped like one.
	 */
	public function object_id(): string {
		$id = $this->data['id'] ?? null;
		return is_string( $id ) && preg_match( '/^(rec|iss)_[0-9a-f]{32}$/', $id ) ? $id : '';
	}

	/**
	 * The issuer the event is about: the issuer itself, or the record's issuer.
	 */
	public function issuer_id(): string {
		if ( 'issuer.paused' === $this->type ) {
			return $this->object_id();
		}
		return is_string( $this->data['issuer_id'] ?? null ) ? $this->data['issuer_id'] : '';
	}
}
