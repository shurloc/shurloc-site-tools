<?php
/**
 * Offline payment order status handling.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Integrations;

use WC_Order;

/**
 * Sets eligible offline payment orders directly to processing.
 */
final class Offline_Payment_Status {

	/**
	 * Processing order status.
	 */
	private const PROCESSING_STATUS = 'processing';

	/**
	 * Registers WooCommerce hooks.
	 */
	public function register(): void {
		add_filter(
			'woocommerce_cheque_process_payment_order_status',
			array( $this, 'set_processing_status' ),
			10,
			2
		);

		add_filter(
			'woocommerce_bacs_process_payment_order_status',
			array( $this, 'set_processing_status' ),
			10,
			2
		);
	}

	/**
	 * Sets eligible offline payment orders to processing.
	 *
	 * @param string   $status Current order status.
	 * @param WC_Order $order  Order.
	 * @return string
	 */
	public function set_processing_status(
		string $status,
		WC_Order $order
	): string {
		unset( $order );

		return self::PROCESSING_STATUS;
	}
}
