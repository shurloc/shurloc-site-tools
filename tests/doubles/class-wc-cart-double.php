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
final class WC_Cart_Double extends WC_Cart {

	/**
	 * Test cart contents.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $test_cart = array();

	/**
	 * Test cart contents total.
	 *
	 * @var float
	 */
	private float $test_cart_contents_total = 0.0;

	/**
	 * Set the test cart contents.
	 *
	 * @param array<string,array<string,mixed>> $cart Cart contents.
	 * @return void
	 */
	public function set_test_cart(
		array $cart
	): void {
		$this->test_cart = $cart;
	}

	/**
	 * Get the cart contents.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_cart(): array {
		return $this->test_cart;
	}

	/**
	 * Set the test cart contents total.
	 *
	 * @param float $total Cart contents total.
	 * @return void
	 */
	public function set_test_cart_contents_total(
		float $total
	): void {
		$this->test_cart_contents_total = $total;
	}

	/**
	 * Get the cart contents total.
	 *
	 * @return float
	 */
	public function get_cart_contents_total(): float {
		return $this->test_cart_contents_total;
	}
}
