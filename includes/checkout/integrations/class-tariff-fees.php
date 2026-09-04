<?php
/**
 * Tariff fee calculation.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Integrations;

use Shurloc\SiteTools\Checkout\Settings\Settings;

/**
 * Adds tariff fees to the WooCommerce cart.
 */
final class Tariff_Fees {

	/**
	 * Mesh product category slug.
	 *
	 * @var string
	 */
	private const MESH_CATEGORY_SLUG = 'shurloc-mesh';

	/**
	 * Sefar product tag slug.
	 *
	 * @var string
	 */
	private const SEFAR_TAG_SLUG = 'sefar';

	/**
	 * Regular mesh tariff fee label.
	 *
	 * @var string
	 */
	private const MESH_TARIFF_LABEL = 'Raw material import tariff';

	/**
	 * Sefar tariff fee label.
	 *
	 * @var string
	 */
	private const SEFAR_TARIFF_LABEL = 'Sefar Mesh Tariff';

	/**
	 * Checkout Tools settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Creates the tariff fee handler.
	 *
	 * @param Settings $settings Checkout Tools settings.
	 */
	public function __construct(
		Settings $settings
	) {
		$this->settings = $settings;
	}

	/**
	 * Registers WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'woocommerce_cart_calculate_fees',
			array( $this, 'add_tariff_fees' )
		);
	}

	/**
	 * Adds applicable tariff fees to the cart.
	 *
	 * @return void
	 */
	public function add_tariff_fees(): void {
		if (
			is_admin() &&
			! defined( 'DOING_AJAX' )
		) {
			return;
		}

		$mesh_total  = 0.0;
		$sefar_total = 0.0;

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if (
				! isset( $cart_item['product_id'] ) ||
				! isset( $cart_item['line_total'] )
			) {
				continue;
			}

			$product_id = (int) $cart_item['product_id'];
			$line_total = (float) $cart_item['line_total'];

			if ( 0 >= $product_id || 0 >= $line_total ) {
				continue;
			}

			/*
			 * Sefar takes precedence over the regular mesh tariff.
			 */
			if (
				has_term(
					self::SEFAR_TAG_SLUG,
					'product_tag',
					$product_id
				)
			) {
				$sefar_total += $line_total;
				continue;
			}

			if (
				has_term(
					self::MESH_CATEGORY_SLUG,
					'product_cat',
					$product_id
				)
			) {
				$mesh_total += $line_total;
			}
		}

		if (
			$this->settings->is_mesh_tariff_enabled() &&
			0 < $mesh_total
		) {
			WC()->cart->add_fee(
				self::MESH_TARIFF_LABEL,
				$mesh_total * $this->settings->get_mesh_tariff_rate()
			);
		}

		if (
			$this->settings->is_sefar_tariff_enabled() &&
			0 < $sefar_total
		) {
			WC()->cart->add_fee(
				self::SEFAR_TARIFF_LABEL,
				$sefar_total * $this->settings->get_sefar_tariff_rate()
			);
		}
	}
}
