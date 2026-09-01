<?php
/**
 * User purchase service.
 *
 * Tracks the most recent qualifying WooCommerce purchase for registered
 * WordPress users.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Services;

defined( 'ABSPATH' ) || exit;

use WC_Order;

/**
 * Tracks the most recent WooCommerce purchase for a user.
 */
final class User_Purchase_Service {

	/**
	 * Last purchase timestamp meta key.
	 *
	 * @var string
	 */
	public const LAST_PURCHASE_META_KEY = 'last_purchase';

	/**
	 * Last purchase order ID meta key.
	 *
	 * @var string
	 */
	public const LAST_PURCHASE_ORDER_META_KEY = 'last_purchase_order';

	/**
	 * Last purchase order status meta key.
	 *
	 * @var string
	 */
	public const LAST_PURCHASE_STATUS_META_KEY = 'last_purchase_status';

	/**
	 * Last purchase order total meta key.
	 *
	 * @var string
	 */
	public const LAST_PURCHASE_TOTAL_META_KEY = 'last_purchase_total';

	/**
	 * Order statuses that establish a qualifying purchase.
	 *
	 * @var string[]
	 */
	public const QUALIFYING_STATUSES = array(
		'on-hold',
		'processing',
		'completed',
	);

	/**
	 * Register WordPress and WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'woocommerce_order_status_on-hold',
			array(
				$this,
				'track_qualifying_order',
			),
			10,
			2
		);

		add_action(
			'woocommerce_order_status_processing',
			array(
				$this,
				'track_qualifying_order',
			),
			10,
			2
		);

		add_action(
			'woocommerce_order_status_completed',
			array(
				$this,
				'track_qualifying_order',
			),
			10,
			2
		);

		add_action(
			'woocommerce_order_status_changed',
			array(
				$this,
				'update_tracked_order_status',
			),
			10,
			4
		);
	}

	/**
	 * Track a qualifying WooCommerce order as the user's last purchase.
	 *
	 * A qualifying order replaces the stored last purchase only when it is
	 * newer than the currently stored order. If two orders have the same
	 * creation timestamp, the greater order ID is treated as newer.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    WooCommerce order.
	 * @return void
	 */
	public function track_qualifying_order(
		int $order_id,
		WC_Order $order
	): void {

		if (
			! in_array(
				$order->get_status(),
				self::QUALIFYING_STATUSES,
				true
			)
		) {
			return;
		}

		$user_id = $order->get_customer_id();

		if ( 0 >= $user_id ) {
			return;
		}

		$date_created = $order->get_date_created();

		if ( null === $date_created ) {
			return;
		}

		$purchase_timestamp = $date_created->getTimestamp();

		$current_timestamp = (int) get_user_meta(
			$user_id,
			self::LAST_PURCHASE_META_KEY,
			true
		);

		$current_order_id = (int) get_user_meta(
			$user_id,
			self::LAST_PURCHASE_ORDER_META_KEY,
			true
		);

		if (
			$current_timestamp > $purchase_timestamp ||
			(
				$current_timestamp === $purchase_timestamp &&
				$current_order_id > $order_id
			)
		) {
			return;
		}

		$this->store_purchase(
			user_id: $user_id,
			order_id: $order_id,
			timestamp: $purchase_timestamp,
			status: $order->get_status(),
			total: (float) $order->get_total(),
		);
	}

	/**
	 * Update the status of the user's currently tracked purchase.
	 *
	 * Qualifying statuses are handled by track_qualifying_order(). This method
	 * handles later transitions such as cancellation, failure, or refund so
	 * the stored status does not become stale.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Previous order status.
	 * @param string   $new_status New order status.
	 * @param WC_Order $order      WooCommerce order.
	 * @return void
	 */
	public function update_tracked_order_status(
		int $order_id,
		string $old_status,
		string $new_status,
		WC_Order $order
	): void {

		unset( $old_status );

		if (
			in_array(
				$new_status,
				self::QUALIFYING_STATUSES,
				true
			)
		) {
			return;
		}

		$user_id = $order->get_customer_id();

		if ( 0 >= $user_id ) {
			return;
		}

		$current_order_id = (int) get_user_meta(
			$user_id,
			self::LAST_PURCHASE_ORDER_META_KEY,
			true
		);

		if ( $order_id !== $current_order_id ) {
			return;
		}

		update_user_meta(
			$user_id,
			self::LAST_PURCHASE_STATUS_META_KEY,
			$new_status
		);

		update_user_meta(
			$user_id,
			self::LAST_PURCHASE_TOTAL_META_KEY,
			(float) $order->get_total()
		);
	}

	/**
	 * Store a WooCommerce order as the user's last purchase snapshot.
	 *
	 * This method writes the supplied order directly and does not compare it
	 * against the currently stored purchase. It is intended for controlled
	 * operations such as data migrations and reseeding.
	 *
	 * @param int      $user_id User ID.
	 * @param WC_Order $order   WooCommerce order.
	 * @return bool True when the snapshot was stored, false when it could not be.
	 */
	public function store_purchase_from_order(
		int $user_id,
		WC_Order $order
	): bool {

		if ( 0 >= $user_id ) {
			return false;
		}

		$date_created = $order->get_date_created();

		if ( null === $date_created ) {
			return false;
		}

		$this->store_purchase(
			user_id: $user_id,
			order_id: $order->get_id(),
			timestamp: $date_created->getTimestamp(),
			status: $order->get_status(),
			total: (float) $order->get_total(),
		);

		return true;
	}

	/**
	 * Store the user's last purchase metadata.
	 *
	 * @param int    $user_id   User ID.
	 * @param int    $order_id  Order ID.
	 * @param int    $timestamp Order creation timestamp.
	 * @param string $status    Order status.
	 * @param float  $total     Order total.
	 * @return void
	 */
	private function store_purchase(
		int $user_id,
		int $order_id,
		int $timestamp,
		string $status,
		float $total
	): void {

		update_user_meta(
			$user_id,
			self::LAST_PURCHASE_META_KEY,
			$timestamp
		);

		update_user_meta(
			$user_id,
			self::LAST_PURCHASE_ORDER_META_KEY,
			$order_id
		);

		update_user_meta(
			$user_id,
			self::LAST_PURCHASE_STATUS_META_KEY,
			$status
		);

		update_user_meta(
			$user_id,
			self::LAST_PURCHASE_TOTAL_META_KEY,
			$total
		);
	}
}
