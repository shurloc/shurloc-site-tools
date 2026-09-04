<?php
/**
 * WooCommerce function test doubles.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );


/**
 * Arguments passed to wc_get_orders() during tests.
 */
$GLOBALS['shurloc_test_wc_get_orders_args'] = array();

/**
 * WooCommerce products available during tests.
 */
$GLOBALS['shurloc_test_products'] = array();

/**
 * Whether the current test request is a product page.
 */
$GLOBALS['shurloc_test_is_product'] = true;

/**
 * Whether the current test request is a product tag archive.
 */
$GLOBALS['shurloc_test_is_product_tag'] = false;

/**
 * Whether the current request is the cart page.
 */
$GLOBALS['shurloc_test_is_cart'] = false;

/**
 * Whether the current request is the checkout page.
 */
$GLOBALS['shurloc_test_is_checkout'] = false;

/**
 * Current WooCommerce product used during tests.
 */
$GLOBALS['product'] = null;


if ( ! function_exists( 'is_product' ) ) {
	/**
	 * Determine whether the current test request is a product page.
	 *
	 * @return bool
	 */
	function is_product(): bool {

		return $GLOBALS['shurloc_test_is_product'];
	}
}

if ( ! function_exists( 'is_product_tag' ) ) {
	/**
	 * Determine whether the current test request is a product tag archive.
	 *
	 * @return bool
	 */
	function is_product_tag(): bool {

		return $GLOBALS['shurloc_test_is_product_tag'];
	}
}

if ( ! function_exists( 'is_cart' ) ) {
	/**
	 * Determines whether the current request is the cart page.
	 *
	 * @return bool
	 */
	function is_cart(): bool {
		return $GLOBALS['shurloc_test_is_cart'] ?? false;
	}
}

if ( ! function_exists( 'is_checkout' ) ) {
	/**
	 * Determines whether the current request is the checkout page.
	 *
	 * @return bool
	 */
	function is_checkout(): bool {
		return $GLOBALS['shurloc_test_is_checkout'] ?? false;
	}
}


if ( ! function_exists( 'wc_get_order_status_name' ) ) {
	/**
	 * Get the display name for an order status.
	 *
	 * @param string $status Order status.
	 * @return string
	 */
	function wc_get_order_status_name(
		string $status
	): string {

		$status = str_replace(
			'-',
			' ',
			$status
		);

		return ucwords( $status );
	}
}

if ( ! function_exists( 'wc_price' ) ) {
	/**
	 * Format a price for display.
	 *
	 * @param float $price Price to format.
	 * @return string
	 */
	function wc_price(
		float $price
	): string {

		return sprintf(
			'$%0.2f',
			$price
		);
	}
}

if ( ! function_exists( 'wc_get_order_statuses' ) ) {
	/**
	 * Get WooCommerce order statuses.
	 *
	 * @return array<string,string>
	 */
	function wc_get_order_statuses(): array {

		return array(
			'wc-pending'    => 'Pending payment',
			'wc-processing' => 'Processing',
			'wc-on-hold'    => 'On hold',
			'wc-completed'  => 'Completed',
			'wc-cancelled'  => 'Cancelled',
			'wc-refunded'   => 'Refunded',
			'wc-failed'     => 'Failed',
		);
	}
}

if ( ! function_exists( 'wc_attribute_label' ) ) {
	/**
	 * Get a display label for a WooCommerce attribute.
	 *
	 * @param string $name Attribute name.
	 * @return string
	 */
	function wc_attribute_label(
		string $name
	): string {

		if ( str_starts_with( $name, 'pa_' ) ) {
			$name = substr( $name, 3 );
		}

		$name = str_replace(
			array( '-', '_' ),
			' ',
			$name
		);

		return ucwords( $name );
	}
}

if ( ! function_exists( 'wc_get_orders' ) ) {
	/**
	 * Get test WooCommerce orders.
	 *
	 * @param array<string,mixed> $args Order query arguments.
	 * @return WC_Order[]
	 */
	function wc_get_orders(
		array $args = array()
	): array {

		$GLOBALS['shurloc_test_wc_get_orders_args'][] = $args;

		$customer_id = isset( $args['customer_id'] )
			? (int) $args['customer_id']
			: 0;

		if (
			! isset( $GLOBALS['shurloc_test_orders'][ $customer_id ] ) ||
			! is_array(
				$GLOBALS['shurloc_test_orders'][ $customer_id ]
			)
		) {
			return array();
		}

		$orders = array_filter(
			$GLOBALS['shurloc_test_orders'][ $customer_id ],
			static function ( mixed $order ): bool {
				return $order instanceof WC_Order;
			}
		);

		$statuses = $args['status'] ?? array();

		if ( is_string( $statuses ) ) {
			$statuses = array( $statuses );
		}

		if ( is_array( $statuses ) && ! empty( $statuses ) ) {
			$orders = array_filter(
				$orders,
				static function ( WC_Order $order ) use ( $statuses ): bool {
					return in_array(
						$order->get_status(),
						$statuses,
						true
					);
				}
			);
		}

		if (
			'date' === ( $args['orderby'] ?? '' ) &&
			'DESC' === ( $args['order'] ?? '' )
		) {
			usort(
				$orders,
				static function (
					WC_Order $first_order,
					WC_Order $second_order
				): int {

					$first_date  = $first_order->get_date_created();
					$second_date = $second_order->get_date_created();

					$first_timestamp = null === $first_date
						? 0
						: $first_date->getTimestamp();

					$second_timestamp = null === $second_date
						? 0
						: $second_date->getTimestamp();

					return $second_timestamp <=> $first_timestamp;
				}
			);
		}

		$limit = isset( $args['limit'] )
			? (int) $args['limit']
			: -1;

		if ( 0 <= $limit ) {
			$orders = array_slice(
				$orders,
				0,
				$limit
			);
		}

		return array_values( $orders );
	}
}

if ( ! function_exists( 'shurloc_register_test_product' ) ) {
	/**
	 * Register a WooCommerce product for testing.
	 *
	 * @param WC_Product $product Product object.
	 * @return void
	 */
	function shurloc_register_test_product(
		WC_Product $product
	): void {

		$GLOBALS['shurloc_test_products'][ $product->get_id() ] = $product;
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	/**
	 * Get a test WooCommerce product.
	 *
	 * @param int $product_id Product ID.
	 * @return WC_Product|false
	 */
	function wc_get_product(
		int $product_id
	): WC_Product|false {

		if (
			! isset(
				$GLOBALS['shurloc_test_products'][ $product_id ]
			)
		) {
			return false;
		}

		$product =
			$GLOBALS['shurloc_test_products'][ $product_id ];

		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		return $product;
	}
}
