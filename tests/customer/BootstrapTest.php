<?php
/**
 * Tests for the Customer domain bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shurloc\SiteTools\Customer\Admin\Admin_Menu;
use Shurloc\SiteTools\Customer\Admin\Carts_Controller;
use Shurloc\SiteTools\Customer\Admin\User_Cart_Column;
use Shurloc\SiteTools\Customer\Admin\User_Filters;
use Shurloc\SiteTools\Customer\Journey\Admin\Journey_Report_Controller;
use Shurloc\SiteTools\Customer\Journey\Admin\Journey_Schema_Admin;
use Shurloc\SiteTools\Customer\Journey\Frontend\Journey_Browser_Assets;
use Shurloc\SiteTools\Customer\Journey\Journey_Retention_Scheduler;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Rest\Journey_Browser_Ingestion_Controller;
use Shurloc\SiteTools\Customer\Journey\Tracking\Journey_Cart_Tracker;
use Shurloc\SiteTools\Customer\Journey\Tracking\Journey_Order_Tracker;
use Shurloc_Test_WPDB;

/**
 * Tests the Customer domain bootstrap.
 */
final class BootstrapTest extends TestCase {
	/**
	 * Original server environment.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_server;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();
		$this->original_server      = $_SERVER;
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';

		$GLOBALS['shurloc_test_actions']               = array();
		$GLOBALS['shurloc_test_enqueued_scripts']      = array();
		$GLOBALS['shurloc_test_inline_scripts']        = array();
		$GLOBALS['shurloc_test_styles']                = array();
		$GLOBALS['shurloc_test_submenu_pages']         = array();
		$GLOBALS['shurloc_test_rest_routes']           = array();
		$GLOBALS['shurloc_test_action_metadata']       = array();
		$GLOBALS['shurloc_test_cron_events']           = array();
		$GLOBALS['shurloc_test_cron_schedule_result']  = true;
		$GLOBALS['shurloc_test_deactivation_hooks']    = array();
		$GLOBALS['shurloc_test_filters']               = array();
		$GLOBALS['shurloc_test_filter_metadata']       = array();
		$GLOBALS['shurloc_test_options']               = array();
		$GLOBALS['shurloc_test_is_admin']              = true;
		$GLOBALS['shurloc_test_doing_ajax']            = false;
		$GLOBALS['shurloc_test_user_capabilities']     = array();
		$GLOBALS['shurloc_test_timezone']              = 'UTC';
		$GLOBALS['shurloc_journey_schema_attempts']    = 0;
		$GLOBALS['shurloc_journey_schema_should_fail'] = true;
		$_GET = array();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_SERVER = $this->original_server;

		$GLOBALS['shurloc_test_actions']               = array();
		$GLOBALS['shurloc_test_enqueued_scripts']      = array();
		$GLOBALS['shurloc_test_inline_scripts']        = array();
		$GLOBALS['shurloc_test_styles']                = array();
		$GLOBALS['shurloc_test_submenu_pages']         = array();
		$GLOBALS['shurloc_test_rest_routes']           = array();
		$GLOBALS['shurloc_test_action_metadata']       = array();
		$GLOBALS['shurloc_test_cron_events']           = array();
		$GLOBALS['shurloc_test_cron_schedule_result']  = true;
		$GLOBALS['shurloc_test_deactivation_hooks']    = array();
		$GLOBALS['shurloc_test_filters']               = array();
		$GLOBALS['shurloc_test_filter_metadata']       = array();
		$GLOBALS['shurloc_test_options']               = array();
		$GLOBALS['shurloc_test_is_admin']              = true;
		$GLOBALS['shurloc_test_doing_ajax']            = false;
		$GLOBALS['shurloc_test_user_capabilities']     = array();
		$GLOBALS['shurloc_test_timezone']              = 'UTC';
		$GLOBALS['shurloc_journey_schema_attempts']    = 0;
		$GLOBALS['shurloc_journey_schema_should_fail'] = true;
		$_GET = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the shared test-only database double.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Verify the Customer bootstrap registers customer services.
	 *
	 * @return void
	 */
	public function test_register_adds_customer_service_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'wp',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'admin_init',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'wp_login',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'woocommerce_after_calculate_totals',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'woocommerce_checkout_order_processed',
			$GLOBALS['shurloc_test_actions']
		);
	}

	/**
	 * Verify the Customer bootstrap registers admin functionality.
	 *
	 * @return void
	 */
	public function test_register_adds_customer_admin_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'admin_menu',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'admin_enqueue_scripts',
			$GLOBALS['shurloc_test_actions']
		);

		$carts_controller_callbacks = array_filter(
			$GLOBALS['shurloc_test_actions']['admin_enqueue_scripts'],
			static function ( mixed $callback ): bool {
				return is_array( $callback ) &&
					isset( $callback[0] ) &&
					$callback[0] instanceof Carts_Controller;
			}
		);

		self::assertCount(
			1,
			$carts_controller_callbacks
		);

		$journey_report_callbacks = array_filter(
			$GLOBALS['shurloc_test_actions']['admin_enqueue_scripts'],
			static function ( mixed $callback ): bool {
				return is_array( $callback ) &&
					isset( $callback[0] ) &&
					$callback[0] instanceof Journey_Report_Controller;
			}
		);

		self::assertCount( 1, $journey_report_callbacks );
		$journey_report_callback = array_values( $journey_report_callbacks )[0];
		self::assertSame( 'enqueue_assets', $journey_report_callback[1] );

		self::assertArrayHasKey(
			'manage_users_columns',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'manage_users_custom_column',
			$GLOBALS['shurloc_test_filters']
		);

		$cart_column_callbacks = array_filter(
			$GLOBALS['shurloc_test_filters']['manage_users_columns'],
			static function ( mixed $callback ): bool {
				return is_array( $callback ) &&
					isset( $callback[0] ) &&
					$callback[0] instanceof User_Cart_Column;
			}
		);

		self::assertCount(
			1,
			$cart_column_callbacks
		);

		self::assertArrayHasKey(
			'manage_users_sortable_columns',
			$GLOBALS['shurloc_test_filters']
		);
	}

	/**
	 * Verify the admin bootstrap injects the Journey report into Customer Tools.
	 *
	 * @return void
	 */
	public function test_admin_bootstrap_wires_customer_journeys_tab(): void {
		$bootstrap = new Bootstrap();
		$bootstrap->register();

		$customer_menu_callbacks = array_filter(
			$GLOBALS['shurloc_test_actions']['admin_menu'],
			static function ( mixed $callback ): bool {
				return is_array( $callback ) &&
					isset( $callback[0] ) &&
					$callback[0] instanceof Admin_Menu;
			}
		);
		self::assertCount( 1, $customer_menu_callbacks );
		$menu_callback = array_values( $customer_menu_callbacks )[0];
		self::assertIsCallable( $menu_callback );
		$menu_callback();

		$customer_pages = array_values(
			array_filter(
				$GLOBALS['shurloc_test_submenu_pages'],
				static fn ( array $page ): bool => 'shurloc-site-tools-customers' === $page['menu_slug']
			)
		);
		self::assertCount( 1, $customer_pages );
		self::assertIsCallable( $customer_pages[0]['callback'] );

		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = Journey_Schema_Migrator::CURRENT_VERSION;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only report database.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();
		$_GET            = array(
			'page' => 'shurloc-site-tools-customers',
			'tab'  => Journey_Report_Controller::TAB_SLUG,
		);

		ob_start();
		$customer_pages[0]['callback']();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'class="nav-tab nav-tab-active"', $output );
		self::assertStringContainsString( 'Customer Journeys', $output );
		self::assertStringContainsString( 'class="wc-customer-search"', $output );
	}

	/**
	 * Verify the Customer bootstrap registers shared user filters.
	 *
	 * @return void
	 */
	public function test_register_adds_user_filter_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'manage_users_extra_tablenav',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			User_Filters::FILTER_CONTROLS_ACTION,
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'pre_get_users',
			$GLOBALS['shurloc_test_actions']
		);
	}

	/**
	 * Verify the Customer bootstrap registers migration handlers.
	 *
	 * @return void
	 */
	public function test_register_adds_migration_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'admin_post_shurloc_run_purchase_migration',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'admin_post_shurloc_run_cart_migration',
			$GLOBALS['shurloc_test_actions']
		);
	}

	/**
	 * Verify the Journey retry, notice, and admin upgrade hooks are wired.
	 *
	 * @return void
	 */
	public function test_register_adds_journey_schema_admin_hooks(): void {
		$bootstrap = new Bootstrap();
		$bootstrap->register();

		self::assertContains(
			array( $bootstrap, 'maybe_migrate_journey_schema' ),
			$GLOBALS['shurloc_test_actions']['admin_init']
		);

		$journey_callbacks = array_filter(
			$GLOBALS['shurloc_test_actions']['admin_notices'],
			static function ( mixed $callback ): bool {
				return is_array( $callback ) &&
					isset( $callback[0] ) &&
					$callback[0] instanceof Journey_Schema_Admin;
			}
		);

		self::assertCount( 1, $journey_callbacks );
		self::assertArrayHasKey(
			'admin_post_shurloc_retry_journey_schema',
			$GLOBALS['shurloc_test_actions']
		);

		$upgrade_index = array_search(
			array( $bootstrap, 'maybe_migrate_journey_schema' ),
			$GLOBALS['shurloc_test_actions']['admin_init'],
			true
		);

		self::assertIsInt( $upgrade_index );
		self::assertSame(
			5,
			$GLOBALS['shurloc_test_action_metadata']['admin_init'][ $upgrade_index ]['priority']
		);
	}

	/**
	 * Verify the browser routes are registered when WordPress initializes REST.
	 *
	 * @return void
	 */
	public function test_register_adds_journey_browser_ingestion_routes(): void {
		$GLOBALS['shurloc_test_is_admin'] = false;
		$bootstrap                        = new Bootstrap();
		$bootstrap->register();

		self::assertCount( 1, $GLOBALS['shurloc_test_actions']['rest_api_init'] );
		self::assertSame( array(), $GLOBALS['shurloc_test_rest_routes'] );
		self::assertSame( array(), $GLOBALS['shurloc_test_options'] );

		$callback = $GLOBALS['shurloc_test_actions']['rest_api_init'][0];
		self::assertIsArray( $callback );
		self::assertInstanceOf( Journey_Browser_Ingestion_Controller::class, $callback[0] );
		self::assertIsCallable( $callback );
		$callback();

		foreach ( array( '/journey/view', '/journey/duration' ) as $path ) {
			$route = $GLOBALS['shurloc_test_rest_routes'][ Journey_Browser_Ingestion_Controller::ROUTE_NAMESPACE . $path ];
			self::assertSame( 'POST', $route['args']['methods'] );
			self::assertIsCallable( $route['args']['callback'] );
			self::assertIsCallable( $route['args']['permission_callback'] );
		}
		self::assertCount( 2, $GLOBALS['shurloc_test_rest_routes'] );
		self::assertSame( array(), $GLOBALS['shurloc_test_options'] );
	}

	/**
	 * Verify bootstrap wiring enqueues the tracker only after schema readiness.
	 *
	 * @return void
	 */
	public function test_storefront_bootstrap_wires_journey_browser_assets(): void {
		$GLOBALS['shurloc_test_is_admin']           = false;
		$GLOBALS['shurloc_test_is_user_logged_in']  = false;
		$GLOBALS['shurloc_journey_test_doing_cron'] = false;

		$bootstrap = new Bootstrap();
		$bootstrap->register();

		self::assertArrayHasKey( 'wp_enqueue_scripts', $GLOBALS['shurloc_test_actions'] );
		self::assertCount( 1, $GLOBALS['shurloc_test_actions']['wp_enqueue_scripts'] );
		$callback = $GLOBALS['shurloc_test_actions']['wp_enqueue_scripts'][0];
		self::assertIsArray( $callback );
		self::assertInstanceOf( Journey_Browser_Assets::class, $callback[0] );
		self::assertSame( 'enqueue_assets', $callback[1] );
		self::assertIsCallable( $callback );
		self::assertSame( array(), $GLOBALS['shurloc_test_enqueued_scripts'] );

		$callback();
		self::assertSame( array(), $GLOBALS['shurloc_test_enqueued_scripts'] );

		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = Journey_Schema_Migrator::CURRENT_VERSION;
		$callback();
		self::assertCount( 1, $GLOBALS['shurloc_test_enqueued_scripts'] );
		self::assertSame( Journey_Browser_Assets::SCRIPT_HANDLE, $GLOBALS['shurloc_test_enqueued_scripts'][0]['handle'] );
		self::assertCount( 1, $GLOBALS['shurloc_test_inline_scripts'] );
	}

	/**
	 * Verify storefront bootstrap activates the approved WooCommerce cart tracker.
	 *
	 * @return void
	 */
	public function test_storefront_bootstrap_wires_journey_cart_tracker(): void {
		$GLOBALS['shurloc_test_is_admin'] = false;

		$bootstrap = new Bootstrap();
		$bootstrap->register();

		$cart_hooks = array(
			'woocommerce_add_to_cart'                     => array( 'added_to_cart', 4 ),
			'woocommerce_cart_item_removed'               => array( 'removed_from_cart', 2 ),
			'woocommerce_after_cart_item_quantity_update' => array( 'quantity_updated', 4 ),
			'woocommerce_cart_item_restored'              => array( 'restored_to_cart', 2 ),
		);

		foreach ( $cart_hooks as $hook => $expected ) {
			self::assertCount( 1, $GLOBALS['shurloc_test_actions'][ $hook ] );
			$callback = $GLOBALS['shurloc_test_actions'][ $hook ][0];
			self::assertIsArray( $callback );
			self::assertInstanceOf( Journey_Cart_Tracker::class, $callback[0] );
			self::assertSame( $expected[0], $callback[1] );
			self::assertSame( $expected[1], $GLOBALS['shurloc_test_action_metadata'][ $hook ][0]['accepted_args'] );
		}

		self::assertSame( array(), $GLOBALS['shurloc_test_options'] );
	}

	/**
	 * Verify storefront bootstrap activates classic and Store API order tracking.
	 *
	 * @return void
	 */
	public function test_storefront_bootstrap_wires_journey_order_tracker(): void {
		$GLOBALS['shurloc_test_is_admin'] = false;

		$bootstrap = new Bootstrap();
		$bootstrap->register();

		$order_hooks = array(
			'woocommerce_checkout_order_created',
			'woocommerce_store_api_checkout_order_created',
			'woocommerce_store_api_checkout_update_order_from_request',
		);

		foreach ( $order_hooks as $hook ) {
			self::assertCount( 1, $GLOBALS['shurloc_test_actions'][ $hook ] );
			$callback = $GLOBALS['shurloc_test_actions'][ $hook ][0];
			self::assertIsArray( $callback );
			self::assertInstanceOf( Journey_Order_Tracker::class, $callback[0] );
			self::assertSame( 'order_created', $callback[1] );
			self::assertSame( 1, $GLOBALS['shurloc_test_action_metadata'][ $hook ][0]['accepted_args'] );
		}

		self::assertSame( array(), $GLOBALS['shurloc_test_options'] );
	}

	/**
	 * Verify bootstrap schedules retention and deactivation removes its events.
	 *
	 * @return void
	 */
	public function test_bootstrap_wires_journey_retention_scheduler_lifecycle(): void {
		$GLOBALS['shurloc_test_is_admin'] = false;

		$bootstrap = new Bootstrap();
		$bootstrap->register();

		foreach (
			array(
				Journey_Retention_Scheduler::CRON_HOOK,
				Journey_Retention_Scheduler::CONTINUATION_HOOK,
			) as $hook
		) {
			self::assertCount( 1, $GLOBALS['shurloc_test_actions'][ $hook ] );
			$callback = $GLOBALS['shurloc_test_actions'][ $hook ][0];
			self::assertIsArray( $callback );
			self::assertInstanceOf( Journey_Retention_Scheduler::class, $callback[0] );
			self::assertSame( 'run', $callback[1] );
		}

		self::assertCount( 1, $GLOBALS['shurloc_test_cron_events'] );
		self::assertSame(
			Journey_Retention_Scheduler::CRON_HOOK,
			$GLOBALS['shurloc_test_cron_events'][0]['hook']
		);
		self::assertSame( 'daily', $GLOBALS['shurloc_test_cron_events'][0]['recurrence'] );

		$plugin_file = SHURLOC_SITE_TOOLS_PATH . 'shurloc-site-tools.php';
		self::assertArrayHasKey( $plugin_file, $GLOBALS['shurloc_test_deactivation_hooks'] );
		$deactivation_callback = $GLOBALS['shurloc_test_deactivation_hooks'][ $plugin_file ];
		self::assertIsArray( $deactivation_callback );
		self::assertInstanceOf( Journey_Retention_Scheduler::class, $deactivation_callback[0] );
		self::assertSame( 'unschedule', $deactivation_callback[1] );

		wp_schedule_single_event(
			time() + Journey_Retention_Scheduler::CONTINUATION_DELAY_SECONDS,
			Journey_Retention_Scheduler::CONTINUATION_HOOK
		);
		self::assertCount( 2, $GLOBALS['shurloc_test_cron_events'] );

		self::assertIsCallable( $deactivation_callback );
		$deactivation_callback();

		self::assertSame( array(), $GLOBALS['shurloc_test_cron_events'] );
	}

	/**
	 * Verify storefront bootstrap does not register Journey schema work.
	 *
	 * @return void
	 */
	public function test_storefront_registers_no_journey_schema_hooks(): void {
		$GLOBALS['shurloc_test_is_admin'] = false;

		$bootstrap = new Bootstrap();
		$bootstrap->register();

		self::assertArrayNotHasKey(
			'admin_notices',
			$GLOBALS['shurloc_test_actions']
		);
		self::assertArrayNotHasKey(
			'admin_post_shurloc_retry_journey_schema',
			$GLOBALS['shurloc_test_actions']
		);
		self::assertNotContains(
			array( $bootstrap, 'maybe_migrate_journey_schema' ),
			$GLOBALS['shurloc_test_actions']['admin_init']
		);

		$journey_report_callbacks = array_filter(
			$GLOBALS['shurloc_test_actions']['admin_enqueue_scripts'],
			static function ( mixed $callback ): bool {
				return is_array( $callback ) &&
					isset( $callback[0] ) &&
					$callback[0] instanceof Journey_Report_Controller;
			}
		);
		self::assertSame( array(), $journey_report_callbacks );
	}

	/**
	 * Verify the admin check attempts a pending migration only once after failure.
	 *
	 * @return void
	 */
	public function test_pending_journey_schema_attempts_upgrade_and_waits_after_failure(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulate a database failure before dbDelta runs.
		$GLOBALS['wpdb'] = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public string $prefix = 'wp_';

			/**
			 * Options table for lock release.
			 *
			 * @var string
			 */
			public string $options = 'wp_options';

			/**
			 * Fail before the schema updater is called.
			 *
			 * @return string Charset SQL.
			 * @throws RuntimeException Always, to simulate a database failure.
			 */
			public function get_charset_collate(): string {
				++$GLOBALS['shurloc_journey_schema_attempts'];
				if ( $GLOBALS['shurloc_journey_schema_should_fail'] ) {
					throw new RuntimeException( 'Simulated schema failure.' );
				}

				return 'DEFAULT CHARACTER SET utf8mb4';
			}

			/**
			 * Return the lock query for the test double.
			 *
			 * @param string $query SQL query.
			 * @param mixed  ...$args Placeholder arguments.
			 * @return string Query.
			 */
			public function prepare( string $query, mixed ...$args ): string {
				unset( $args );
				return $query;
			}

			/**
			 * Release the simulated migration lock.
			 *
			 * @param string $query SQL query.
			 * @return int Deleted rows.
			 */
			public function query( string $query ): int {
				unset( $query );
				unset( $GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::LOCK_OPTION ] );
				return 1;
			}
		};

		$bootstrap = new Bootstrap();
		$bootstrap->register();
		$bootstrap->maybe_migrate_journey_schema();

		self::assertSame( 1, $GLOBALS['shurloc_journey_schema_attempts'] );
		self::assertSame(
			'migration_failed',
			$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::FAILURE_OPTION ]
		);
		self::assertArrayNotHasKey(
			Journey_Schema_Migrator::VERSION_OPTION,
			$GLOBALS['shurloc_test_options']
		);

		$bootstrap->maybe_migrate_journey_schema();
		self::assertSame( 1, $GLOBALS['shurloc_journey_schema_attempts'] );
	}

	/**
	 * Verify admin checks skip AJAX, ready, newer, failed, and unauthorized requests.
	 *
	 * @return void
	 */
	public function test_admin_upgrade_check_skips_ineligible_requests(): void {
		$bootstrap = new Bootstrap();
		$bootstrap->register();

		$GLOBALS['shurloc_test_doing_ajax'] = true;
		$bootstrap->maybe_migrate_journey_schema();
		self::assertSame( array(), $GLOBALS['shurloc_test_options'] );
		$GLOBALS['shurloc_test_doing_ajax'] = false;

		$GLOBALS['shurloc_test_user_capabilities']['manage_options'] = false;
		$bootstrap->maybe_migrate_journey_schema();
		self::assertSame( array(), $GLOBALS['shurloc_test_options'] );

		$GLOBALS['shurloc_test_user_capabilities']['manage_options']                = true;
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 1;
		$bootstrap->maybe_migrate_journey_schema();
		self::assertArrayNotHasKey(
			Journey_Schema_Migrator::FAILURE_OPTION,
			$GLOBALS['shurloc_test_options']
		);

		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 2;
		$bootstrap->maybe_migrate_journey_schema();
		self::assertArrayNotHasKey(
			Journey_Schema_Migrator::FAILURE_OPTION,
			$GLOBALS['shurloc_test_options']
		);

		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 0;
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::FAILURE_OPTION ] = 'migration_failed';
		$bootstrap->maybe_migrate_journey_schema();
		self::assertSame(
			'migration_failed',
			$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::FAILURE_OPTION ]
		);
	}

	/**
	 * Verify the Customer bootstrap registers the Site Tools overview section.
	 *
	 * @return void
	 */
	public function test_register_adds_site_tools_overview_section(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'shurloc_site_tools_overview',
			$GLOBALS['shurloc_test_actions']
		);
	}
}
