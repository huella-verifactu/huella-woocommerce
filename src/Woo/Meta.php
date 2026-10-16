<?php
/**
 * Order meta written by the plugin. Kept on uninstall: it is part of the shop's tax records.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Woo;

final class Meta {

	public const STATUS         = '_huella_status';
	public const RECORD_ID      = '_huella_record_id';
	public const HUELLA         = '_huella_huella';
	public const ENVIRONMENT    = '_huella_env';
	public const QR_URL         = '_huella_qr_url';
	public const INVOICE_NUMBER = '_huella_invoice_number'; // As registered: later edits are detected.
	public const INVOICE_DATE   = '_huella_invoice_date';
	public const FAILURES       = '_huella_failures';
	public const LAST_ERROR     = '_huella_last_error';
	public const CHANGED        = '_huella_invoice_changed'; // Number or date edited after registration.
}
