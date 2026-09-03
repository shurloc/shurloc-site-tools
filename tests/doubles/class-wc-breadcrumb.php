<?php
/**
 * WooCommerce breadcrumb test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

if ( ! class_exists( 'WC_Breadcrumb' ) ) {

	/**
	 * WooCommerce breadcrumb test double.
	 */
	class WC_Breadcrumb {

		/**
		 * Generate breadcrumbs.
		 *
		 * @return void
		 */
		public function generate(): void {
		}

		/**
		 * Get configured test breadcrumbs.
		 *
		 * @return array<int, array<int, string>>
		 */
		public function get_breadcrumb(): array {

			return $GLOBALS['shurloc_test_wc_breadcrumbs'] ?? array();
		}
	}
}
