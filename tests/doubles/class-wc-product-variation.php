<?php
/**
 * WooCommerce variation product test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

if ( ! class_exists( 'WC_Product_Variation' ) ) {

	/**
	 * WooCommerce variation product test double.
	 */
	class WC_Product_Variation extends WC_Product {

		/**
		 * Variation attributes.
		 *
		 * @var array<string,string>
		 */
		private array $variation_attributes = array();

		/**
		 * Set variation attributes.
		 *
		 * @param array<string,string> $attributes Attributes.
		 * @return void
		 */
		public function set_variation_attributes(
			array $attributes
		): void {

			$this->variation_attributes = $attributes;
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
			return $this->variation_attributes;
		}
	}
}
