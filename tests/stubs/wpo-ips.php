<?php
/**
 * PHPStan stubs for the parts of PDF Invoices & Packing Slips 5.x the plugin calls (SPEC 6.3).
 * Signatures checked against the 5.16.4 source. Never loaded at runtime.
 *
 * @package Huella\Verifactu
 */

namespace WPO\IPS\Documents {

	class DocumentNumber {
		public function get_formatted(): string {
			return '';
		}
	}

	class OrderDocument {
		/** @return string */
		public function get_type() {
			return '';
		}

		/** @return bool */
		public function exists() {
			return false;
		}

		/** @return DocumentNumber|null */
		public function get_number( $document_type = '', $order = null, $context = 'view', $formatted = false ) {
			return null;
		}

		/** @return \WC_DateTime|null */
		public function get_date( $document_type = '', $order = null, $context = 'view', $formatted = false ) {
			return null;
		}
	}
}

namespace {

	/**
	 * @param \WC_Order|int $order
	 * @return \WPO\IPS\Documents\OrderDocument|false
	 */
	function wcpdf_get_document( string $document_type, $order, bool $init = false ) {
		return false;
	}
}
