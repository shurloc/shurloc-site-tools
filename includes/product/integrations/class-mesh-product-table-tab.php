<?php
/**
 * Mesh product table WooCommerce tab.
 *
 * Registers the Mesh Specifications product tab.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use Shurloc\SiteTools\Product\Shortcodes\Mesh_Product_Table_Shortcode_Interface;
use WC_Product;

/**
 * Mesh product table WooCommerce tab.
 */
final class Mesh_Product_Table_Tab {

	/**
	 * Mesh product table shortcode.
	 *
	 * @var Mesh_Product_Table_Shortcode_Interface
	 */
	private Mesh_Product_Table_Shortcode_Interface $shortcode;

	/**
	 * Constructor.
	 *
	 * @param Mesh_Product_Table_Shortcode_Interface $shortcode Mesh table shortcode.
	 */
	public function __construct(
		Mesh_Product_Table_Shortcode_Interface $shortcode
	) {
		$this->shortcode = $shortcode;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'woocommerce_product_tabs',
			array(
				$this,
				'register_tab',
			)
		);
	}

	/**
	 * Register the mesh specifications tab.
	 *
	 * @param array<string,mixed> $tabs Existing tabs.
	 * @return array<string,mixed>
	 */
	public function register_tab(
		array $tabs
	): array {

		global $product;

		if ( ! $product instanceof WC_Product ) {
			return $tabs;
		}

		$html = $this->shortcode->render();

		if ( '' === $html ) {
			return $tabs;
		}

		$tabs['shurloc_mesh_specifications'] = array(
			'title'    => __( 'Mesh Specifications', 'shurloc-site-tools' ),
			'priority' => 35,
			'callback' => array(
				$this,
				'render_tab',
			),
		);

		return $tabs;
	}

	/**
	 * Render the tab.
	 *
	 * @return void
	 */
	public function render_tab(): void {

		$html = $this->shortcode->render();

		// The renderer is responsible for escaping all dynamic output.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}
}
