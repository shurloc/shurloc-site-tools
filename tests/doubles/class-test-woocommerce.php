<?php
/**
 * WooCommerce test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout;

use Test_WC_Cart;
use WooCommerce;

/**
 * Test WooCommerce instance.
 */
final class Test_WooCommerce extends WooCommerce {

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
