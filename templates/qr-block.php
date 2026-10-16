<?php
/**
 * The AEAT QR block in the invoice PDF (SPEC 6.7). Rendered by Dompdf: inline styles only.
 *
 * Override it by copying this file to yourtheme/huella-verifactu/qr-block.php. Keep the
 * legends, the image size and the error correction: the AEAT checks them.
 *
 * @package Huella\Verifactu
 *
 * @var \Huella\Verifactu\QrImage $qr_image The image and its printed size.
 * @var string                    $qr_url   The AEAT validation URL it encodes.
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="huella-verifactu-qr" style="margin: 3mm 0; border-collapse: collapse;">
	<tr>
		<td style="text-align: center; font-size: 8pt; padding: 0;">
			<div style="margin-bottom: 1mm;">QR tributario:</div>
			<img src="<?php echo esc_attr( $qr_image->data_uri ); ?>"
				alt="QR tributario"
				style="width: <?php echo esc_attr( (string) $qr_image->size_mm ); ?>mm; height: <?php echo esc_attr( (string) $qr_image->size_mm ); ?>mm;">
			<div style="margin-top: 1mm; font-weight: bold;">VERI*FACTU</div>
		</td>
	</tr>
</table>
