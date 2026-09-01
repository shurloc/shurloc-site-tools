<?php
/**
 * WooCommerce cart test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

/**
 * WooCommerce cart test double.
 */
class WC_Cart {

	/**
	 * Get the cart contents.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_cart(): array {
		return array();
	}

	/**
	 * Get the cart contents total.
	 *
	 * @return float
	 */
	public function get_cart_contents_total(): float {
		return 0.0;
	}
}
