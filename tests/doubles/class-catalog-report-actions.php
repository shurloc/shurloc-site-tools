<?php
/**
 * Catalog report actions test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

/**
 * Test double for catalog report actions.
 */
final class Catalog_Report_Actions_Double implements Catalog_Report_Actions_Interface {

	/**
	 * Recorded action calls.
	 *
	 * @var string[]
	 */
	private array $calls = array();

	/**
	 * Record export request.
	 *
	 * @return void
	 */
	public function export_variations(): void {

		$this->calls[] = 'export_variations';
	}

	/**
	 * Record report generation request.
	 *
	 * @return void
	 */
	public function generate_catalog_report(): void {

		$this->calls[] = 'generate_catalog_report';
	}

	/**
	 * Retrieve recorded calls.
	 *
	 * @return string[]
	 */
	public function get_calls(): array {

		return $this->calls;
	}
}
