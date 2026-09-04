<?php
/**
 * Checkout domain bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Checkout\Admin\Admin_Menu;
use Shurloc\SiteTools\Checkout\Admin\Admin_Page_Controller;
use Shurloc\SiteTools\Checkout\Admin\Settings_Page;
use Shurloc\SiteTools\Checkout\Frontend\Tariff_Tooltips;
use Shurloc\SiteTools\Checkout\Integrations\Offline_Payment_Status;
use Shurloc\SiteTools\Checkout\Integrations\Payment_Gateway_Labels;
use Shurloc\SiteTools\Checkout\Integrations\Payment_Processing_Fee;
use Shurloc\SiteTools\Checkout\Integrations\Tariff_Fees;
use Shurloc\SiteTools\Checkout\Settings\Settings;

/**
 * Bootstraps the Checkout domain.
 */
final class Bootstrap {

	/**
	 * Register the Checkout domain.
	 *
	 * @return void
	 */
	public function register(): void {

		$settings = new Settings();

		$settings_page = new Settings_Page(
			settings: $settings,
		);
		$settings_page->register();

		$admin_page = new Admin_Page_Controller(
			settings_page: $settings_page,
		);

		$admin_menu = new Admin_Menu(
			checkout_page: $admin_page,
		);
		$admin_menu->register();

		$tariff_fees = new Tariff_Fees(
			settings: $settings,
		);
		$tariff_fees->register();

		$tariff_tooltips = new Tariff_Tooltips(
			settings: $settings,
		);
		$tariff_tooltips->register();

		$payment_processing_fee = new Payment_Processing_Fee();
		$payment_processing_fee->register();

		$payment_labels = new Payment_Gateway_Labels();
		$payment_labels->register();

		$offline_payment_status = new Offline_Payment_Status();
		$offline_payment_status->register();
	}
}
