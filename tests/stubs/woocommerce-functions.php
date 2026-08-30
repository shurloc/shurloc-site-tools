<?php
/**
 * WooCommerce function test doubles.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

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
