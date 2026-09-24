<?php
/**
 * Customer domain bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Admin\Admin_Menu;
use Shurloc\SiteTools\Customer\Admin\Admin_Page_Controller;
use Shurloc\SiteTools\Customer\Admin\Cart_Details_Renderer;
use Shurloc\SiteTools\Customer\Admin\Carts_Controller;
use Shurloc\SiteTools\Customer\Admin\Customer_Migrations_Controller;
use Shurloc\SiteTools\Customer\Admin\User_Activity_Columns;
use Shurloc\SiteTools\Customer\Admin\User_Activity_Filters;
use Shurloc\SiteTools\Customer\Admin\User_Cart_Column;
use Shurloc\SiteTools\Customer\Admin\User_Columns;
use Shurloc\SiteTools\Customer\Admin\User_Filters;
use Shurloc\SiteTools\Customer\Admin\User_Phone_Column;
use Shurloc\SiteTools\Customer\Admin\User_Purchase_Columns;
use Shurloc\SiteTools\Customer\Admin\User_Purchase_Filters;
use Shurloc\SiteTools\Customer\Formatters\Relative_Time_Formatter;
use Shurloc\SiteTools\Customer\Journey\Admin\Journey_Report_Controller;
use Shurloc\SiteTools\Customer\Journey\Admin\Journey_Report_Renderer;
use Shurloc\SiteTools\Customer\Journey\Admin\Journey_Schema_Admin;
use Shurloc\SiteTools\Customer\Journey\Journey_Privacy_Eraser;
use Shurloc\SiteTools\Customer\Journey\Journey_Privacy_Exporter;
use Shurloc\SiteTools\Customer\Journey\Journey_Report_Page_Builder;
use Shurloc\SiteTools\Customer\Journey\Journey_Retention_Scheduler;
use Shurloc\SiteTools\Customer\Journey\Frontend\Journey_Browser_Assets;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Session_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Visitor_Repository;
use Shurloc\SiteTools\Customer\Journey\Rest\Journey_Browser_Ingestion_Controller;
use Shurloc\SiteTools\Customer\Journey\Tracking\Journey_Cart_Tracker;
use Shurloc\SiteTools\Customer\Journey\Tracking\Journey_Order_Tracker;
use Shurloc\SiteTools\Customer\Migrations\User_Cart_Migration;
use Shurloc\SiteTools\Customer\Migrations\User_Purchase_Migration;
use Shurloc\SiteTools\Customer\Repositories\Cart_Session_Repository;
use Shurloc\SiteTools\Customer\Services\Cart_Listing_Service;
use Shurloc\SiteTools\Customer\Services\User_Activity_Service;
use Shurloc\SiteTools\Customer\Services\User_Cart_Service;
use Shurloc\SiteTools\Customer\Services\User_Purchase_Service;

/**
 * Bootstraps the Customer domain.
 */
final class Bootstrap {
	/**
	 * Migrator shared by the admin upgrade check and retry controller.
	 *
	 * @var Journey_Schema_Migrator|null
	 */
	private ?Journey_Schema_Migrator $journey_schema_migrator = null;

	/**
	 * Register the Customer domain.
	 *
	 * @return void
	 */
	public function register(): void {
		$journey_report_controller = null;

		$journey_browser_ingestion = new Journey_Browser_Ingestion_Controller();
		$journey_browser_ingestion->register();

		$journey_browser_assets = new Journey_Browser_Assets();
		$journey_browser_assets->register();

		$journey_cart_tracker = new Journey_Cart_Tracker();
		$journey_cart_tracker->register();

		$journey_order_tracker = new Journey_Order_Tracker();
		$journey_order_tracker->register();

		$journey_privacy_exporter = new Journey_Privacy_Exporter();
		$journey_privacy_exporter->register();

		$journey_privacy_eraser = new Journey_Privacy_Eraser();
		$journey_privacy_eraser->register();

		$journey_retention_scheduler = new Journey_Retention_Scheduler();
		$journey_retention_scheduler->register();

		register_deactivation_hook(
			SHURLOC_SITE_TOOLS_PATH . 'shurloc-site-tools.php',
			array( $journey_retention_scheduler, 'unschedule' )
		);

		if ( is_admin() ) {
			$this->journey_schema_migrator = new Journey_Schema_Migrator();

			$journey_schema_admin = new Journey_Schema_Admin(
				migrator: $this->journey_schema_migrator,
			);
			$journey_schema_admin->register();

			$journey_report_controller = new Journey_Report_Controller(
				report_repository: new Journey_Report_Repository(
					schema_migrator: $this->journey_schema_migrator,
				),
				session_repository: new Journey_Report_Session_Repository(
					schema_migrator: $this->journey_schema_migrator,
				),
				visitor_repository: new Journey_Report_Visitor_Repository(
					schema_migrator: $this->journey_schema_migrator,
				),
				page_builder: new Journey_Report_Page_Builder(),
				renderer: new Journey_Report_Renderer(),
				timezone: wp_timezone(),
			);
			$journey_report_controller->register();

			add_action(
				'admin_init',
				array( $this, 'maybe_migrate_journey_schema' ),
				5
			);
		}

		$relative_time_formatter = new Relative_Time_Formatter();
		$cart_details_renderer   = new Cart_Details_Renderer();

		$user_activity_service = new User_Activity_Service();
		$user_activity_service->register();

		$user_purchase_service = new User_Purchase_Service();
		$user_purchase_service->register();

		$user_cart_service = new User_Cart_Service();
		$user_cart_service->register();

		$user_purchase_migration = new User_Purchase_Migration(
			purchase_service: $user_purchase_service,
		);

		$user_cart_migration = new User_Cart_Migration(
			cart_service: $user_cart_service,
		);

		$migrations_controller = new Customer_Migrations_Controller(
			purchase_migration: $user_purchase_migration,
			cart_migration: $user_cart_migration,
		);
		$migrations_controller->register();

		$cart_session_repository = new Cart_Session_Repository();

		$cart_listing_service = new Cart_Listing_Service(
			cart_session_repository: $cart_session_repository,
		);

		$carts_controller = new Carts_Controller(
			listing_service: $cart_listing_service,
			session_repository: $cart_session_repository,
			cart_details_renderer: $cart_details_renderer,
			time_formatter: $relative_time_formatter,
		);
		$carts_controller->register();

		$customer_page = new Admin_Page_Controller(
			migrations_controller: $migrations_controller,
			carts_controller: $carts_controller,
			journey_report_controller: $journey_report_controller,
		);

		$admin_menu = new Admin_Menu(
			customer_page: $customer_page,
		);
		$admin_menu->register();

		$user_activity_columns = new User_Activity_Columns(
			time_formatter: $relative_time_formatter,
		);
		$user_activity_columns->register();

		$user_purchase_columns = new User_Purchase_Columns(
			time_formatter: $relative_time_formatter,
		);
		$user_purchase_columns->register();

		$user_cart_column = new User_Cart_Column(
			cart_details_renderer: $cart_details_renderer,
		);
		$user_cart_column->register();

		$user_filters = new User_Filters();
		$user_filters->register();

		$user_activity_filters = new User_Activity_Filters();
		$user_activity_filters->register();

		$user_purchase_filters = new User_Purchase_Filters();
		$user_purchase_filters->register();

		$user_columns = new User_Columns();
		$user_columns->register();

		$user_phone_column = new User_Phone_Column();
		$user_phone_column->register();
	}

	/**
	 * Upgrade pending Journey schemas on an authorized admin page request.
	 *
	 * A recorded failure waits for the explicit retry action. This prevents a
	 * broken schema from causing another expensive attempt on every admin page.
	 * AJAX requests wait for a normal admin page load.
	 *
	 * @return void
	 */
	public function maybe_migrate_journey_schema(): void {
		if (
			null === $this->journey_schema_migrator ||
			wp_doing_ajax() ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		if (
			$this->journey_schema_migrator->get_installed_version() >=
				Journey_Schema_Migrator::CURRENT_VERSION ||
			'' !== $this->journey_schema_migrator->get_failure_code()
		) {
			return;
		}

		$this->journey_schema_migrator->migrate();
	}
}
