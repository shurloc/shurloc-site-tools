<?php
/**
 * Divi WooCommerce breadcrumb separator.
 *
 * Replaces Divi Woo Breadcrumb separator text nodes with a solid SVG arrow.
 *
 * The Divi Woo Breadcrumb module must use "/" as its separator.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces Divi breadcrumb separators with SVG arrows.
 */
final class Breadcrumb_Separator {

	/**
	 * Asset handle.
	 *
	 * @var string
	 */
	private const ASSET_HANDLE = 'shurloc-breadcrumb-separator';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
	}

	/**
	 * Enqueue the separator CSS and JavaScript.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {

		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			self::ASSET_HANDLE,
			SHURLOC_SITE_TOOLS_URL . 'assets/product/css/shurloc-breadcrumb-separator.css',
			array(),
			SHURLOC_SITE_TOOLS_VERSION
		);

		wp_enqueue_script(
			self::ASSET_HANDLE,
			SHURLOC_SITE_TOOLS_URL . 'assets/product/js/shurloc-breadcrumb-separator.js',
			array(),
			SHURLOC_SITE_TOOLS_VERSION,
			true
		);
	}
}
