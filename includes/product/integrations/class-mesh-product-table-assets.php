<?php
/**
 * Mesh product table assets.
 *
 * Registers frontend assets for the mesh product table.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

/**
 * Mesh product table assets.
 */
final class Mesh_Product_Table_Assets {

	/**
	 * Stylesheet handle.
	 */
	public const STYLE_HANDLE = 'shurloc-mesh-product-table';

	/**
	 * Script handle.
	 */
	public const SCRIPT_HANDLE = 'shurloc-mesh-product-table';

	/**
	 * Asset URL.
	 *
	 * @var string
	 */
	private string $asset_url;

	/**
	 * Asset version.
	 *
	 * @var string
	 */
	private string $asset_version;

	/**
	 * Constructor.
	 *
	 * @param string $asset_url     Base asset URL.
	 * @param string $asset_version Asset version.
	 */
	public function __construct(
		string $asset_url,
		string $asset_version
	) {

		$this->asset_url     = $asset_url;
		$this->asset_version = $asset_version;
	}

	/**
	 * Register frontend hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'wp_enqueue_scripts',
			array(
				$this,
				'register_assets',
			)
		);
	}

	/**
	 * Register frontend assets.
	 *
	 * The assets are registered globally and only enqueued by the shortcode
	 * when a mesh specification table is actually rendered.
	 *
	 * @return void
	 */
	public function register_assets(): void {

		wp_register_style(
			self::STYLE_HANDLE,
			$this->asset_url . 'assets/product/css/shurloc-mesh-product-table.css',
			array(),
			$this->asset_version
		);

		wp_register_script(
			self::SCRIPT_HANDLE,
			$this->asset_url . 'assets/product/js/shurloc-mesh-product-table.js',
			array(),
			$this->asset_version,
			true
		);
	}
}
