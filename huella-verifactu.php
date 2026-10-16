<?php
/**
 * Plugin Name:          Huella – Verifactu para WooCommerce
 * Plugin URI:           https://github.com/huella-verifactu/huella-woocommerce
 * Description:          Registra las facturas de PDF Invoices & Packing Slips en la AEAT (VERI*FACTU) a través de Huella Cloud y añade el QR tributario al PDF.
 * Version:              0.1.0
 * Requires at least:    6.4
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce, woocommerce-pdf-invoices-packing-slips
 * WC requires at least: 8.9
 * Author:               Samuil Vinitsiy Petrov
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          huella-verifactu
 * Domain Path:          /languages
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'HUELLA_VERIFACTU_VERSION', '0.1.0' );
define( 'HUELLA_VERIFACTU_FILE', __FILE__ );

require __DIR__ . '/autoload.php';

\Huella\Verifactu\Plugin::boot( __FILE__ );
