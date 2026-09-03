<?php
/**
 * Extended WooCommerce cart test double.
 *
 * Provides test-only controls for WooCommerce cart state.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

if ( ! class_exists( 'Test_WC_Cart' ) ) {

	/**
	 * Extended WooCommerce product test double.
	 */
	class Test_WC_Cart extends WC_Cart {

		/**
		 * Cart items.
		 *
		 * @var array<int,array<string,mixed>>
		 */
		private array $test_cart = array();

		/**
		 * Set cart items.
		 *
		 * @param array<int,array<string,mixed>> $cart Cart items.
		 *
		 * @return void
		 */
		public function set_cart(
			array $cart
		): void {

			$this->test_cart = $cart;
		}

		/**
		 * Get cart items.
		 *
		 * @return array<int,array<string,mixed>>
		 */
		public function get_cart(): array {

			return $this->test_cart;
		}
	}
}
