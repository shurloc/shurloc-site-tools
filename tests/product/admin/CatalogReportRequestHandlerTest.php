<?php
/**
 * Tests for catalog report request handling.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Tests admin request routing.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class CatalogReportRequestHandlerTest extends TestCase {

	/**
	 * Request handler.
	 *
	 * @var Catalog_Report_Request_Handler
	 */
	private Catalog_Report_Request_Handler $handler;

	/**
	 * Actions double.
	 *
	 * @var Catalog_Report_Actions_Double
	 */
	private Catalog_Report_Actions_Double $actions;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_nonce_checks']    = array();

		$_POST = array();

		$this->actions = new Catalog_Report_Actions_Double();

		$this->handler = new Catalog_Report_Request_Handler(
			actions: $this->actions,
		);

		add_action(
			'admin_init',
			array(
				$this->handler,
				'handle_request',
			)
		);
	}

	/**
	 * Request handler registers admin_init hook.
	 */
	public function test_registers_admin_init_hook(): void {

		$this->assertArrayHasKey(
			'admin_init',
			$GLOBALS['shurloc_test_actions']
		);

		$this->assertIsCallable(
			$GLOBALS['shurloc_test_actions']['admin_init'][0]
		);
	}

	/**
	 * Request handler ignores missing action.
	 */
	public function test_request_without_action_is_ignored(): void {

		$_POST = array();

		$this->handler->handle_request();

		$this->assertSame(
			array(),
			$this->actions->get_calls()
		);
	}

	/**
	 * Export request routes correctly.
	 */
	public function test_export_request_routes_to_export_handler(): void {

		$_POST = array(
			'shurloc_action' => 'export_variations',
		);

		$this->handler->handle_request();

		$this->assertSame(
			array(
				'export_variations',
			),
			$this->actions->get_calls()
		);
	}

	/**
	 * Report request routes correctly.
	 */
	public function test_report_request_routes_to_report_handler(): void {

		$_POST = array(
			'shurloc_action' => 'generate_catalog_report',
		);

		$this->handler->handle_request();

		$this->assertSame(
			array(
				'generate_catalog_report',
			),
			$this->actions->get_calls()
		);
	}
}
