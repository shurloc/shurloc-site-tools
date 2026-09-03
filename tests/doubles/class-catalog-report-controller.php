<?php
/**
 * Catalog report controller test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

use Shurloc\SiteTools\Shared\Interfaces\Admin_Page_Interface;

/**
 * Catalog report controller test double.
 */
final class Catalog_Report_Controller_Double implements
	Catalog_Report_Actions_Interface,
	Admin_Page_Interface {

	/**
	 * Number of times the page was rendered.
	 *
	 * @var int
	 */
	public int $render_count = 0;

	/**
	 * Number of times variations were exported.
	 *
	 * @var int
	 */
	public int $export_variations_count = 0;

	/**
	 * Number of times a catalog report was generated.
	 *
	 * @var int
	 */
	public int $generate_catalog_report_count = 0;

	/**
	 * Render the Product Tools admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {

		++$this->render_count;
	}

	/**
	 * Export WooCommerce catalog variations.
	 *
	 * @return void
	 */
	public function export_variations(): void {

		++$this->export_variations_count;
	}

	/**
	 * Generate a catalog analysis report.
	 *
	 * @return void
	 */
	public function generate_catalog_report(): void {

		++$this->generate_catalog_report_count;
	}
}
