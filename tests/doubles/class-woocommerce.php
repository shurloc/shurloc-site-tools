<?php
/**
 * WooCommerce test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

/**
 * WooCommerce test double.
 */
class WooCommerce {

	/**
	 * Current cart.
	 *
	 * @var WC_Cart|null
	 */
	public ?WC_Cart $cart = null;
}
