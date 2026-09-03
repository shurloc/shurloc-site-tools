<?php
/**
 * Catalog report actions interface.
 *
 * Defines actions that can be triggered by catalog report requests.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

/**
 * Catalog report actions contract.
 */
interface Catalog_Report_Actions_Interface {

	/**
	 * Export WooCommerce catalog variations.
	 *
	 * @return void
	 */
	public function export_variations(): void;

	/**
	 * Generate catalog analysis report.
	 *
	 * @return void
	 */
	public function generate_catalog_report(): void;
}
