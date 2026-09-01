<?php
/**
 * Extended WooCommerce product test double.
 *
 * Provides test-only controls for WooCommerce product state.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

if ( ! class_exists( 'Test_WC_Product' ) ) {

	/**
	 * Extended WooCommerce product test double.
	 */
	class Test_WC_Product extends WC_Product {

		/**
		 * Product visibility.
		 *
		 * @var bool
		 */
		private bool $test_visible = true;

		/**
		 * Product type.
		 *
		 * @var string
		 */
		private string $test_type = 'simple';

		/**
		 * Child product IDs.
		 *
		 * @var int[]
		 */
		private array $test_children = array();

		/**
		 * Product category.
		 *
		 * @var string
		 */
		private string $test_category = '';

		/**
		 * Set product visibility.
		 *
		 * Test-only helper used to control the value returned by is_visible().
		 *
		 * @param bool $visible Whether the product is visible.
		 * @return void
		 */
		public function set_visible(
			bool $visible
		): void {

			$this->test_visible = $visible;
		}

		/**
		 * Determine whether the product is visible.
		 *
		 * @return bool Whether the product is visible.
		 */
		public function is_visible(): bool {

			return $this->test_visible;
		}

		/**
		 * Set product type.
		 *
		 * Test-only helper used to control the value returned by is_type().
		 *
		 * @param string $type Product type.
		 * @return void
		 */
		public function set_type(
			string $type
		): void {

			$this->test_type = $type;
		}

		/**
		 * Determine whether the product matches a product type.
		 *
		 * @param mixed $type Requested product type.
		 * @return bool Whether the product matches the requested type.
		 */
		public function is_type(
			mixed $type
		): bool {

			return $this->test_type === $type;
		}

		/**
		 * Set child product IDs.
		 *
		 * @param int[] $children Child IDs.
		 * @return void
		 */
		public function set_children(
			array $children
		): void {

			$this->test_children = $children;
		}

		/**
		 * Get child product IDs.
		 *
		 * @return int[] Child IDs.
		 */
		public function get_children(): array {

			return $this->test_children;
		}

		/**
		 * Set product category.
		 *
		 * @param string $category Product category.
		 * @return void
		 */
		public function set_category(
			string $category
		): void {

			$this->test_category = $category;
		}

		/**
		 * Get product category.
		 *
		 * @return string Product category.
		 */
		public function get_category(): string {

			return $this->test_category;
		}
	}
}
