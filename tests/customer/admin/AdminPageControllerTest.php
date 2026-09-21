<?php
/**
 * Tests for the customer admin page controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Formatters\Relative_Time_Formatter;
use Shurloc\SiteTools\Customer\Journey\Admin\Journey_Report_Controller;
use Shurloc\SiteTools\Customer\Journey\Admin\Journey_Report_Renderer;
use Shurloc\SiteTools\Customer\Journey\Journey_Report_Page_Builder;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Session_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Visitor_Repository;
use Shurloc\SiteTools\Customer\Migrations\User_Cart_Migration;
use Shurloc\SiteTools\Customer\Migrations\User_Purchase_Migration;
use Shurloc\SiteTools\Customer\Repositories\Cart_Session_Repository;
use Shurloc\SiteTools\Customer\Services\Cart_Listing_Service;
use Shurloc\SiteTools\Customer\Services\User_Cart_Service;
use Shurloc\SiteTools\Customer\Services\User_Purchase_Service;
use Shurloc_Test_WPDB;

/**
 * Tests the customer admin page controller.
 */
final class AdminPageControllerTest extends TestCase {

	/**
	 * Admin page controller under test.
	 *
	 * @var Admin_Page_Controller
	 */
	private Admin_Page_Controller $controller;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_options']      = array();
		$GLOBALS['shurloc_test_nonce_fields'] = array();
		$GLOBALS['shurloc_test_filters']      = array();
		$GLOBALS['shurloc_test_products']     = array();
		$GLOBALS['shurloc_test_users']        = array();
		$GLOBALS['shurloc_test_permalinks']   = array();
		$GLOBALS['shurloc_test_time']         = \time();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		$_GET = array();

		$purchase_service =
			new User_Purchase_Service();

		$cart_service =
			new User_Cart_Service();

		$purchase_migration =
			new User_Purchase_Migration(
				purchase_service: $purchase_service,
			);

		$cart_migration =
			new User_Cart_Migration(
				cart_service: $cart_service,
			);

		$migrations_controller =
			new Customer_Migrations_Controller(
				purchase_migration: $purchase_migration,
				cart_migration: $cart_migration,
			);

		$repository = new Cart_Session_Repository();

		$carts_controller = new Carts_Controller(
			listing_service: new Cart_Listing_Service(
				cart_session_repository: $repository,
			),
			session_repository: $repository,
			cart_details_renderer: new Cart_Details_Renderer(),
			time_formatter: new Relative_Time_Formatter(),
		);

		$this->controller =
			new Admin_Page_Controller(
				migrations_controller: $migrations_controller,
				carts_controller: $carts_controller,
				journey_report_controller: new Journey_Report_Controller(
					report_repository: new Journey_Report_Repository(),
					session_repository: new Journey_Report_Session_Repository(),
					visitor_repository: new Journey_Report_Visitor_Repository(),
					page_builder: new Journey_Report_Page_Builder(),
					renderer: new Journey_Report_Renderer(),
					timezone: new DateTimeZone( 'UTC' ),
					now: new DateTimeImmutable( '2026-09-21 12:00:00', new DateTimeZone( 'UTC' ) )
				),
			);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_options']      = array();
		$GLOBALS['shurloc_test_nonce_fields'] = array();
		$GLOBALS['shurloc_test_filters']      = array();
		$GLOBALS['shurloc_test_products']     = array();
		$GLOBALS['shurloc_test_users']        = array();
		$GLOBALS['shurloc_test_permalinks']   = array();
		$GLOBALS['shurloc_test_time']         = 0;

		$_GET = array();

		parent::tearDown();
	}

	/**
	 * Verify the overview tab is displayed by default.
	 *
	 * @return void
	 */
	public function test_render_page_displays_overview_by_default(): void {

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Customer Tools',
			$output
		);

		self::assertStringContainsString(
			'Customer tools, listed by operational importance.',
			$output
		);

		self::assertStringContainsString(
			'Customer activity, purchase, and cart tracking.',
			$output
		);

		self::assertStringContainsString(
			'<ul class="ul-disc">',
			$output
		);

		self::assertStringNotContainsString(
			'Customer Data Migrations',
			$output
		);
	}

	/**
	 * Verify the migrations tab renders the migrations controller.
	 *
	 * @return void
	 */
	public function test_render_page_displays_migrations_tab(): void {

		$_GET['page'] = 'shurloc-site-tools-customers';
		$_GET['tab']  = 'migrations';

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Customer Data Migrations',
			$output
		);

		self::assertStringContainsString(
			'Purchase Tracking Seeding',
			$output
		);

		self::assertStringNotContainsString(
			'Customer tools, listed by operational importance.',
			$output
		);
	}

	/**
	 * Verify the Carts tab renders the operational cart table.
	 *
	 * @return void
	 */
	public function test_render_page_displays_carts_tab(): void {

		$_GET['page'] = 'shurloc-site-tools-customers';
		$_GET['tab']  = 'carts';

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'class="widefat fixed striped shurloc-carts-table"',
			$output
		);

		self::assertStringContainsString(
			'No non-empty carts were found.',
			$output
		);

		self::assertStringNotContainsString(
			'Customer tools, listed by operational importance.',
			$output
		);

		self::assertStringNotContainsString(
			'Customer Data Migrations',
			$output
		);
	}

	/**
	 * Verify the Journeys tab renders bounded report selectors.
	 *
	 * @return void
	 */
	public function test_render_page_displays_journeys_tab(): void {

		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = Journey_Schema_Migrator::CURRENT_VERSION;
		$_GET['page'] = 'shurloc-site-tools-customers';
		$_GET['tab']  = Journey_Report_Controller::TAB_SLUG;

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Customer Journeys',
			$output
		);

		self::assertStringContainsString(
			'class="wc-customer-search"',
			$output
		);

		self::assertStringContainsString(
			'name="journey_visitor_id"',
			$output
		);

		self::assertStringNotContainsString(
			'Customer tools, listed by operational importance.',
			$output
		);
	}

	/**
	 * Verify an invalid tab falls back to the overview tab.
	 *
	 * @return void
	 */
	public function test_render_page_invalid_tab_falls_back_to_overview(): void {

		$_GET['page'] = 'shurloc-site-tools-customers';
		$_GET['tab']  = 'invalid-tab';

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Customer tools, listed by operational importance.',
			$output
		);

		self::assertStringNotContainsString(
			'Customer Data Migrations',
			$output
		);
	}

	/**
	 * Verify the overview tab is active by default.
	 *
	 * @return void
	 */
	public function test_overview_tab_is_active_by_default(): void {

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		self::assertMatchesRegularExpression(
			'/tab=overview[^"]*"[^>]*class="nav-tab nav-tab-active"/',
			$output
		);
	}

	/**
	 * Verify the migrations tab is active when selected.
	 *
	 * @return void
	 */
	public function test_migrations_tab_is_active_when_selected(): void {

		$_GET['page'] = 'shurloc-site-tools-customers';
		$_GET['tab']  = 'migrations';

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		self::assertMatchesRegularExpression(
			'/tab=migrations[^"]*"[^>]*class="nav-tab nav-tab-active"/',
			$output
		);
	}

	/**
	 * Verify the Carts tab is active when selected.
	 *
	 * @return void
	 */
	public function test_carts_tab_is_active_when_selected(): void {

		$_GET['page'] = 'shurloc-site-tools-customers';
		$_GET['tab']  = 'carts';

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		self::assertMatchesRegularExpression(
			'/tab=carts[^\"]*"[^>]*class="nav-tab nav-tab-active"/',
			$output
		);
	}

	/**
	 * Verify the Journeys tab is active when selected.
	 *
	 * @return void
	 */
	public function test_journeys_tab_is_active_when_selected(): void {

		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = Journey_Schema_Migrator::CURRENT_VERSION;
		$_GET['page'] = 'shurloc-site-tools-customers';
		$_GET['tab']  = Journey_Report_Controller::TAB_SLUG;

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		self::assertMatchesRegularExpression(
			'/tab=journeys[^\"]*"[^>]*class="nav-tab nav-tab-active"/',
			$output
		);
	}
}
