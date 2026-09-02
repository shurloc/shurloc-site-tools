<?php
/**
 * Tests for catalog report controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Analyzers\Catalog_Analyzer;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;
use Shurloc\SiteTools\Product\Services\Catalog_Analysis_Service;
use Shurloc\SiteTools\Product\Services\Product_Catalog_Service;

/**
 * Tests catalog report admin controller.
 */
final class CatalogReportControllerTest extends TestCase {

	/**
	 * Catalog report controller.
	 *
	 * @var Catalog_Report_Controller
	 */
	private Catalog_Report_Controller $controller;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();

		$catalog_service = new Product_Catalog_Service();

		$analysis_service = new Catalog_Analysis_Service(
			catalog_service: new $catalog_service(),
			catalog_analyzer: new Catalog_Analyzer(
				mesh_parser: new Mesh_Parser(),
			),
		);

		$this->controller = new Catalog_Report_Controller(
			catalog_service: $catalog_service,
			analysis_service: $analysis_service,
		);
	}

	/**
	 * Controller should register admin hooks.
	 */
	public function test_controller_registers_admin_hooks(): void {

		$this->controller->register();

		$this->assertArrayHasKey(
			'admin_init',
			$GLOBALS['shurloc_test_actions']
		);
	}

	/**
	 * Admin init callback should be callable.
	 */
	public function test_admin_init_callback_is_registered(): void {

		$this->controller->register();

		$this->assertIsCallable(
			$GLOBALS['shurloc_test_actions']['admin_init'][0]
		);
	}
}
