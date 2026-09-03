<?php
/**
 * Extended WooCommerce cart test double.
 *
 * Provides test-only controls for WooCommerce cart state.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

use Shurloc\SiteTools\Checkout\Test_Fee;

if ( ! class_exists( 'Test_WC_Cart' ) ) {

	/**
	 * Extended WooCommerce product test double.
	 */
	class Test_WC_Cart extends WC_Cart {

		/**
		 * Cart items.
		 *
		 * @var array<int|string,array<string,mixed>>
		 */
		private array $test_cart = array();

		/**
		 * Added fees.
		 *
		 * @var array<int,array{name: string, amount: float, taxable: bool}>
		 */
		private array $fees = array();

		/**
		 * Existing fees.
		 *
		 * @var Test_Fee[]
		 */
		private array $existing_fees = array();

		/**
		 * Cart contents total.
		 *
		 * @var float
		 */
		private float $cart_contents_total = 0.0;

		/**
		 * Shipping total.
		 *
		 * @var float
		 */
		private float $shipping_total = 0.0;

		/**
		 * Cart taxes.
		 *
		 * @var array<string,float>
		 */
		private array $taxes = array();

		/**
		 * Set cart items.
		 *
		 * @param array<int|string,array<string,mixed>> $cart Cart items.
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
		 * @return array<int|string,array<string,mixed>>
		 */
		public function get_cart(): array {

			return $this->test_cart;
		}

		/**
		 * Set the cart contents total.
		 *
		 * @param float $total Cart contents total.
		 * @return void
		 */
		public function set_cart_contents_total(
			float $total
		): void {

			$this->cart_contents_total = $total;
		}

		/**
		 * Get the cart contents total.
		 *
		 * @return float
		 */
		public function get_cart_contents_total(): float {

			return $this->cart_contents_total;
		}

		/**
		 * Set the shipping total.
		 *
		 * @param float $total Shipping total.
		 * @return void
		 */
		public function set_shipping_total(
			float $total
		): void {

			$this->shipping_total = $total;
		}

		/**
		 * Get the shipping total.
		 *
		 * @return float
		 */
		public function get_shipping_total(): float {

			return $this->shipping_total;
		}

		/**
		 * Set existing cart fees.
		 *
		 * @param Test_Fee[] $fees Existing fees.
		 * @return void
		 */
		public function set_existing_fees(
			array $fees
		): void {

			$this->existing_fees = $fees;
		}

		/**
		 * Get existing cart fees.
		 *
		 * @return Test_Fee[]
		 */
		public function get_fees(): array {

			return $this->existing_fees;
		}

		/**
		 * Set cart taxes.
		 *
		 * @param array<string,float> $taxes Cart taxes.
		 * @return void
		 */
		public function set_taxes(
			array $taxes
		): void {

			$this->taxes = $taxes;
		}

		/**
		 * Get cart taxes.
		 *
		 * @return array<string,float>
		 */
		public function get_taxes(): array {

			return $this->taxes;
		}

		/**
		 * Add a fee.
		 *
		 * @param string $name    Fee name.
		 * @param float  $amount  Fee amount.
		 * @param bool   $taxable Whether the fee is taxable.
		 * @return void
		 */
		public function add_fee(
			string $name,
			float $amount,
			bool $taxable = false
		): void {

			$this->fees[] = array(
				'name'    => $name,
				'amount'  => $amount,
				'taxable' => $taxable,
			);
		}

		/**
		 * Get fees added to the cart.
		 *
		 * @return array<int,array{name: string, amount: float, taxable: bool}>
		 */
		public function get_added_fees(): array {

			return $this->fees;
		}
	}
}
