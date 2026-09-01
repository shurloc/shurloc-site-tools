<?php
/**
 * User cart service.
 *
 * Tracks the most recent WooCommerce cart snapshot for logged-in users.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Services;

defined( 'ABSPATH' ) || exit;

use WC_Cart;
use WC_Order;
use WC_Product;

/**
 * Tracks a user's most recently known WooCommerce cart state.
 *
 * @phpstan-type CartSnapshotItem array{
 *     cart_item_key:string,
 *     product_id:int,
 *     variation_id:int,
 *     name:string,
 *     sku:string,
 *     quantity:int,
 *     line_subtotal:float,
 *     line_total:float,
 *     variation:array<string,string>
 * }
 */
final class User_Cart_Service {

	/**
	 * Cart item count meta key.
	 *
	 * @var string
	 */
	public const CART_COUNT_META_KEY = 'cart_item_count';

	/**
	 * Cart contents total meta key.
	 *
	 * @var string
	 */
	public const CART_TOTAL_META_KEY = 'cart_contents_total';

	/**
	 * Cart contents meta key.
	 *
	 * @var string
	 */
	public const CART_ITEMS_META_KEY = 'cart_contents';

	/**
	 * Cart updated timestamp meta key.
	 *
	 * @var string
	 */
	public const CART_UPDATED_META_KEY = 'cart_updated';

	/**
	 * Cart snapshot version meta key.
	 *
	 * @var string
	 */
	public const CART_VERSION_META_KEY = 'cart_snapshot_version';

	/**
	 * Cart expiration timestamp meta key.
	 *
	 * @var string
	 */
	public const CART_EXPIRES_META_KEY = 'cart_expires';

	/**
	 * Cart snapshot schema version.
	 *
	 * @var int
	 */
	private const CART_SNAPSHOT_VERSION = 1;

	/**
	 * Cart snapshot expiration period in days.
	 *
	 * @var int
	 */
	private const CART_EXPIRATION_DAYS = 30;

	/**
	 * Number of seconds in one day.
	 *
	 * @var int
	 */
	private const DAY_IN_SECONDS = 86400;

	/**
	 * Register WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'woocommerce_after_calculate_totals',
			array(
				$this,
				'update_cart_snapshot',
			)
		);

		add_action(
			'woocommerce_checkout_order_processed',
			array(
				$this,
				'clear_cart_snapshot_after_purchase',
			),
			10,
			1
		);
	}

	/**
	 * Store a user's normalized cart snapshot.
	 *
	 * This method writes the supplied cart snapshot directly and is suitable for
	 * controlled operations such as migrations and reseeding.
	 *
	 * @param int                         $user_id        User ID.
	 * @param array<int,CartSnapshotItem> $cart_contents Normalized cart contents.
	 * @param float                       $contents_total Cart contents total.
	 * @param int                         $updated_at     Snapshot update timestamp.
	 * @param int                         $expires_at     Snapshot expiration timestamp.
	 * @return bool True when the snapshot was stored.
	 */
	public function store_cart_snapshot(
		int $user_id,
		array $cart_contents,
		float $contents_total,
		int $updated_at,
		int $expires_at
	): bool {

		if (
			0 >= $user_id ||
			empty( $cart_contents )
		) {
			return false;
		}

		$item_count = $this->get_item_count(
			cart_contents: $cart_contents,
		);

		update_user_meta(
			$user_id,
			self::CART_COUNT_META_KEY,
			$item_count
		);

		update_user_meta(
			$user_id,
			self::CART_TOTAL_META_KEY,
			$contents_total
		);

		update_user_meta(
			$user_id,
			self::CART_ITEMS_META_KEY,
			$cart_contents
		);

		update_user_meta(
			$user_id,
			self::CART_UPDATED_META_KEY,
			$updated_at
		);

		update_user_meta(
			$user_id,
			self::CART_VERSION_META_KEY,
			self::CART_SNAPSHOT_VERSION
		);

		update_user_meta(
			$user_id,
			self::CART_EXPIRES_META_KEY,
			$expires_at
		);

		return true;
	}

	/**
	 * Update the current user's cart snapshot.
	 *
	 * @param WC_Cart $cart WooCommerce cart.
	 * @return void
	 */
	public function update_cart_snapshot(
		WC_Cart $cart
	): void {

		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( 0 >= $user_id ) {
			return;
		}

		$cart_contents = $this->get_cart_contents(
			cart: $cart,
		);

		if ( empty( $cart_contents ) ) {
			$this->clear_cart_snapshot(
				user_id: $user_id,
			);

			return;
		}

		$timestamp = time();

		$expiration_timestamp = $timestamp
			+ (
				self::DAY_IN_SECONDS
				* self::CART_EXPIRATION_DAYS
			);

		$this->store_cart_snapshot(
			user_id: $user_id,
			cart_contents: $cart_contents,
			contents_total: (float) $cart->get_cart_contents_total(),
			updated_at: $timestamp,
			expires_at: $expiration_timestamp,
		);
	}

	/**
	 * Clear the cart snapshot after a purchase.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function clear_cart_snapshot_after_purchase(
		int $order_id
	): void {

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( 0 >= $user_id ) {
			return;
		}

		$this->clear_cart_snapshot(
			user_id: $user_id,
		);
	}

	/**
	 * Get normalized cart contents.
	 *
	 * @param WC_Cart $cart WooCommerce cart.
	 * @return array<int,CartSnapshotItem>
	 */
	private function get_cart_contents(
		WC_Cart $cart
	): array {

		$cart_contents = array();

		foreach ( $cart->get_cart() as $item_key => $item ) {

			if (
				! isset( $item['data'] ) ||
				! $item['data'] instanceof WC_Product
			) {
				continue;
			}

			$product = $item['data'];

			$quantity = isset( $item['quantity'] )
				? (int) $item['quantity']
				: 0;

			if ( 0 >= $quantity ) {
				continue;
			}

			$cart_contents[] = array(
				'cart_item_key' => (string) $item_key,
				'product_id'    => isset( $item['product_id'] )
					? (int) $item['product_id']
					: 0,
				'variation_id'  => isset( $item['variation_id'] )
					? (int) $item['variation_id']
					: 0,
				'name'          => $product->get_name(),
				'sku'           => $product->get_sku(),
				'quantity'      => $quantity,
				'line_subtotal' => isset( $item['line_subtotal'] )
					? (float) $item['line_subtotal']
					: 0.0,
				'line_total'    => isset( $item['line_total'] )
					? (float) $item['line_total']
					: 0.0,
				'variation'     => $this->get_variation_attributes(
					cart_item: $item,
				),
			);
		}

		return $cart_contents;
	}

	/**
	 * Get the total quantity represented by normalized cart contents.
	 *
	 * @param array<int,CartSnapshotItem> $cart_contents Normalized cart contents.
	 * @return int
	 */
	private function get_item_count(
		array $cart_contents
	): int {

		$item_count = 0;

		foreach ( $cart_contents as $item ) {
			$item_count += $item['quantity'];
		}

		return $item_count;
	}

	/**
	 * Get normalized variation attributes from a cart item.
	 *
	 * @param array<string,mixed> $cart_item WooCommerce cart item.
	 * @return array<string,string>
	 */
	private function get_variation_attributes(
		array $cart_item
	): array {

		if (
			! isset( $cart_item['variation'] ) ||
			! is_array( $cart_item['variation'] )
		) {
			return array();
		}

		$variation = array();

		foreach ( $cart_item['variation'] as $attribute => $value ) {

			if (
				! is_string( $attribute ) ||
				! is_string( $value )
			) {
				continue;
			}

			$variation[ $attribute ] = $value;
		}

		return $variation;
	}

	/**
	 * Clear a user's stored cart snapshot.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function clear_cart_snapshot(
		int $user_id
	): void {

		delete_user_meta(
			$user_id,
			self::CART_COUNT_META_KEY
		);

		delete_user_meta(
			$user_id,
			self::CART_TOTAL_META_KEY
		);

		delete_user_meta(
			$user_id,
			self::CART_ITEMS_META_KEY
		);

		delete_user_meta(
			$user_id,
			self::CART_UPDATED_META_KEY
		);

		delete_user_meta(
			$user_id,
			self::CART_VERSION_META_KEY
		);

		delete_user_meta(
			$user_id,
			self::CART_EXPIRES_META_KEY
		);
	}
}
