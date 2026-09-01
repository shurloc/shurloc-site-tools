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
use Shurloc\SiteTools\Customer\Migrations\User_Cart_Migration;
use Shurloc\SiteTools\Customer\Migrations\User_Purchase_Migration;
use Shurloc\SiteTools\Customer\Services\User_Activity_Service;
use Shurloc\SiteTools\Customer\Services\User_Cart_Service;
use Shurloc\SiteTools\Customer\Services\User_Purchase_Service;

/**
 * Bootstraps the Customer domain.
 */
final class Bootstrap {

	/**
	 * Register the Customer domain.
	 *
	 * @return void
	 */
	public function register(): void {

		$relative_time_formatter = new Relative_Time_Formatter();

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

		$customer_page = new Admin_Page_Controller(
			migrations_controller: $migrations_controller,
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

		$user_cart_column = new User_Cart_Column();
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
}
