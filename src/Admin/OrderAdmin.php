<?php
/**
 * The order screens (SPEC 6.10): a "Verifactu" column in the orders list, a meta box on the
 * order, and the "Reintentar envío" action. Works with HPOS and with the legacy posts screens.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

namespace Huella\Verifactu\Admin;

use Huella\Verifactu\Sending\Scheduler;
use Huella\Verifactu\Sending\Status;
use Huella\Verifactu\Woo\Meta;
use WC_Order;
use WP_Post;

final class OrderAdmin {

	public const COLUMN = 'huella_verifactu';
	public const RETRY  = 'huella_verifactu_retry';

	/** @var Scheduler */
	private static $scheduler;

	public static function register( Scheduler $scheduler ): void {
		self::$scheduler = $scheduler;
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( self::class, 'columns' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( self::class, 'column_hpos' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( self::class, 'columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( self::class, 'column_legacy' ), 10, 2 );
		add_action( 'add_meta_boxes', array( self::class, 'add_meta_box' ) );
		add_action( 'admin_post_' . self::RETRY, array( self::class, 'retry' ) );
	}

	/**
	 * @param array<string, string> $columns List columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$result = array();
		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$result[ self::COLUMN ] = 'Verifactu';
			}
		}
		if ( ! isset( $result[ self::COLUMN ] ) ) {
			$result[ self::COLUMN ] = 'Verifactu';
		}
		return $result;
	}

	/**
	 * @param string $column Column key.
	 * @param mixed  $order  The order (HPOS list).
	 */
	public static function column_hpos( $column, $order ): void {
		if ( self::COLUMN === $column && $order instanceof WC_Order ) {
			echo StatusLabel::badge( (string) $order->get_meta( Meta::STATUS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in badge()
		}
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Order ID (legacy list).
	 */
	public static function column_legacy( $column, $post_id ): void {
		if ( self::COLUMN === $column ) {
			self::column_hpos( $column, wc_get_order( (int) $post_id ) );
		}
	}

	public static function add_meta_box(): void {
		add_meta_box( 'huella-verifactu', 'Verifactu', array( self::class, 'meta_box' ), wc_get_page_screen_id( 'shop-order' ), 'side', 'default' );
	}

	/**
	 * @param WP_Post|WC_Order|mixed $object The order (HPOS) or its post (legacy).
	 */
	public static function meta_box( $object ): void {
		$order = $object instanceof WC_Order ? $object : ( $object instanceof WP_Post ? wc_get_order( $object->ID ) : null );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$status = (string) $order->get_meta( Meta::STATUS );
		echo '<p>' . StatusLabel::badge( $status ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in badge()
		if ( '' === $status ) {
			echo '<p>' . esc_html__( 'Sin factura todavía: se registra cuando PDF Invoices crea la factura.', 'huella-verifactu' ) . '</p>';
			return;
		}
		$environment = (string) $order->get_meta( Meta::ENVIRONMENT );
		if ( '' !== $environment ) {
			printf( '<p>%s: %s</p>', esc_html__( 'Entorno', 'huella-verifactu' ), esc_html( 'live' === $environment ? __( 'producción', 'huella-verifactu' ) : __( 'pruebas', 'huella-verifactu' ) ) );
		}
		$huella = (string) $order->get_meta( Meta::HUELLA );
		if ( '' !== $huella ) {
			printf( '<p>%s: <code title="%s">%s…</code></p>', esc_html__( 'Huella', 'huella-verifactu' ), esc_attr( $huella ), esc_html( substr( $huella, 0, 12 ) ) );
		}
		$qr_url = (string) $order->get_meta( Meta::QR_URL );
		if ( '' !== $qr_url ) {
			printf( '<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>', esc_url( $qr_url ), esc_html__( 'Comprobar en la AEAT', 'huella-verifactu' ) );
		}
		$error = (string) $order->get_meta( Meta::LAST_ERROR );
		if ( '' !== $error && '' === (string) $order->get_meta( Meta::RECORD_ID ) ) {
			printf( '<p style="color:#8a2424;">%s</p>', esc_html( $error ) );
		}
		if ( StatusLabel::can_retry( $status ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::RETRY . '&order_id=' . $order->get_id() ), self::RETRY . '_' . $order->get_id() );
			printf( '<p><a class="button" href="%s">%s</a></p>', esc_url( $url ), esc_html__( 'Reintentar envío', 'huella-verifactu' ) );
		}
	}

	/**
	 * "Reintentar envío": back to pending and queue a send. Never touches a registered order.
	 */
	public static function retry(): void {
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified next
		check_admin_referer( self::RETRY . '_' . $order_id );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'No tienes permiso para editar pedidos.', 'huella-verifactu' ), 403 );
		}
		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order && '' === (string) $order->get_meta( Meta::RECORD_ID ) && StatusLabel::can_retry( (string) $order->get_meta( Meta::STATUS ) ) ) {
			self::requeue( $order );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wc-orders' ) );
		exit;
	}

	public static function requeue( WC_Order $order ): void {
		$order->update_meta_data( Meta::STATUS, Status::PENDING );
		$order->delete_meta_data( Meta::FAILURES );
		$order->save();
		$order->add_order_note(
			sprintf(
				/* translators: %s: user name */
				__( 'Verifactu: reintento de envío pedido por %s.', 'huella-verifactu' ),
				wp_get_current_user()->display_name
			)
		);
		self::$scheduler->send_soon( $order->get_id() );
	}
}
