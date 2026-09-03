<?php
/**
 * Order buyer company integration.
 *
 * Adds the billing company to the WooCommerce admin order buyer name.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Adds billing company information to admin order buyer names.
 */
final class Order_Buyer_Company_Integration {

	/**
	 * Register WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'woocommerce_admin_order_buyer_name',
			array(
				$this,
				'add_billing_company',
			),
			10,
			2
		);
	}

	/**
	 * Add the billing company to the displayed buyer name.
	 *
	 * Orders without a billing company retain the existing buyer name.
	 *
	 * @param string   $buyer Buyer display name.
	 * @param WC_Order $order WooCommerce order.
	 * @return string
	 */
	public function add_billing_company(
		string $buyer,
		WC_Order $order
	): string {

		$company = trim(
			(string) $order->get_billing_company()
		);

		if ( '' === $company ) {
			return $buyer;
		}

		return $buyer . ' - ' . $company;
	}
}
