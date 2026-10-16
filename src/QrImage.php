<?php
/**
 * The QR image for the invoice PDF (SPEC 3.7, 6.7), the same rules as huella core's qr module:
 * error correction M (never boosted), a 4-module quiet zone around the symbol, and a printed
 * size that makes the symbol itself 35 mm (the AEAT allows 30 to 40 mm).
 *
 * PNG through GD at about 300 dpi; SVG when GD is missing. Both go into the PDF as a data URI,
 * so nothing is written to disk and nothing is fetched by Dompdf.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu;

use Huella\Verifactu\Vendor\chillerlan\QRCode\Common\EccLevel;
use Huella\Verifactu\Vendor\chillerlan\QRCode\Output\QROutputInterface;
use Huella\Verifactu\Vendor\chillerlan\QRCode\QRCode;
use Huella\Verifactu\Vendor\chillerlan\QRCode\QROptions;

final class QrImage {

	public const SYMBOL_MM     = 35;
	public const QUIET_MODULES = 4; // ISO/IEC 18004 minimum, on each side.
	private const DPI          = 300;
	private const MM_PER_INCH  = 25.4;

	/** @var string data: URI of the image. */
	public $data_uri;
	/** @var float Printed width and height in mm, quiet zone included. */
	public $size_mm;

	private function __construct( string $data_uri, float $size_mm ) {
		$this->data_uri = $data_uri;
		$this->size_mm  = $size_mm;
	}

	public static function for_url( string $url, ?bool $png = null ): self {
		$png     = $png ?? extension_loaded( 'gd' );
		$modules = self::symbol_modules( $url );
		$scale   = max( 1, (int) ceil( self::SYMBOL_MM / self::MM_PER_INCH * self::DPI / $modules ) );
		$options = new QROptions(
			array(
				'eccLevel'      => EccLevel::M,
				'outputType'    => $png ? QROutputInterface::GDIMAGE_PNG : QROutputInterface::MARKUP_SVG,
				'outputBase64'  => true,
				'addQuietzone'  => true,
				'quietzoneSize' => self::QUIET_MODULES,
				'scale'         => $scale,
			)
		);
		$size = self::SYMBOL_MM * ( $modules + 2 * self::QUIET_MODULES ) / $modules;
		return new self( ( new QRCode( $options ) )->render( $url ), round( $size, 2 ) );
	}

	/**
	 * Modules per side of the symbol alone (the version picked for this URL at level M).
	 */
	public static function symbol_modules( string $url ): int {
		$options = new QROptions(
			array(
				'eccLevel'     => EccLevel::M,
				'addQuietzone' => false,
			)
		);
		$qrcode = new QRCode( $options );
		$qrcode->addByteSegment( $url );
		return $qrcode->getQRMatrix()->getSize();
	}
}
