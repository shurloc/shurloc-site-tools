<?php
/**
 * WooCommerce test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout;

use Test_WC_Cart;

/**
 * Test WooCommerce instance.
 */
final class Test_WooCommerce {

	/**
	 * Test cart.
	 *
	 * @var Test_WC_Cart
	 */
	public Test_WC_Cart $cart;

	/**
	 * Test session.
	 *
	 * @var Test_Session
	 */
	public Test_Session $session;

	/**
	 * Creates the test WooCommerce instance.
	 */
	public function __construct() {
		$this->cart    = new Test_WC_Cart();
		$this->session = new Test_Session();
	}
}
