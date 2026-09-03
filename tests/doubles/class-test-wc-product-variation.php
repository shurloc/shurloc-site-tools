<?php
/**
 * Extended WooCommerce product variation test double.
 *
 * Adds helper methods used only by tests that are not part of the real
 * WooCommerce WC_Product_Variation API.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

if ( ! class_exists( 'Test_WC_Product_Variation' ) ) {

	/**
	 * Extended WooCommerce product variation test double.
	 */
	class Test_WC_Product_Variation extends WC_Product_Variation {

		/**
		 * Variation attributes.
		 *
		 * @var array<string,string>
		 */
		private array $test_variation_attributes = array();

		/**
		 * Product type.
		 *
		 * @var string
		 */
		private string $test_type = 'simple';

		/**
		 * Set variation attributes.
		 *
		 * @param array<string,string> $attributes Attributes.
		 * @return void
		 */
		public function set_variation_attributes(
			array $attributes
		): void {

			$this->test_variation_attributes = $attributes;
		}

		/**
		 * Get variation attributes.
		 *
		 * @param mixed $with_prefix Prependattribute_ or not.
		 * @return array<string|int,mixed>
		 */
		public function get_variation_attributes(
			mixed $with_prefix = true
		): array {
			unset( $with_prefix );
			return $this->test_variation_attributes;
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
	}
}
