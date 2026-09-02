<?php
/**
 * Mesh product table shortcode.
 *
 * Provides the [shurloc_mesh_table] shortcode.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Shortcodes;

use Shurloc\SiteTools\Product\Factories\Mesh_Table_Data_Factory;
use Shurloc\SiteTools\Product\Integrations\Mesh_Product_Table_Assets;
use Shurloc\SiteTools\Product\Renderers\Mesh_Product_Table_Renderer_Interface;
use Shurloc\SiteTools\Product\Services\Mesh_Product_Data_Service_Interface;
use WC_Product;

/**
 * Mesh product table shortcode.
 */
final class Mesh_Product_Table_Shortcode implements Mesh_Product_Table_Shortcode_Interface {

	/**
	 * Mesh product data service.
	 *
	 * @var Mesh_Product_Data_Service_Interface
	 */
	private Mesh_Product_Data_Service_Interface $data_service;

	/**
	 * Mesh table data factory.
	 *
	 * @var Mesh_Table_Data_Factory
	 */
	private Mesh_Table_Data_Factory $table_data_factory;

	/**
	 * Mesh product table renderer.
	 *
	 * @var Mesh_Product_Table_Renderer_Interface
	 */
	private Mesh_Product_Table_Renderer_Interface $renderer;

	/**
	 * Constructor.
	 *
	 * @param Mesh_Product_Data_Service_Interface   $data_service       Mesh product data service.
	 * @param Mesh_Table_Data_Factory               $table_data_factory Mesh table data factory.
	 * @param Mesh_Product_Table_Renderer_Interface $renderer           Table renderer.
	 */
	public function __construct(
		Mesh_Product_Data_Service_Interface $data_service,
		Mesh_Table_Data_Factory $table_data_factory,
		Mesh_Product_Table_Renderer_Interface $renderer
	) {

		$this->data_service       = $data_service;
		$this->table_data_factory = $table_data_factory;
		$this->renderer           = $renderer;
	}

	/**
	 * Register shortcode.
	 *
	 * @return void
	 */
	public function register(): void {

		add_shortcode(
			'shurloc_mesh_table',
			array(
				$this,
				'render',
			)
		);
	}

	/**
	 * Render shortcode.
	 *
	 * @param array<string,mixed> $attributes Shortcode attributes.
	 * @return string
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Squiz.Commenting.FunctionComment.Missing
	public function render(
		array $attributes = array()
	): string {

		global $product;

		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$result = $this->data_service->analyze_product(
			$product
		);

		if ( ! $result->is_mesh_product() ) {
			return '';
		}

		$data = $this->table_data_factory->create(
			$result
		);

		if ( ! $data->has_rows() ) {
			return '';
		}

		wp_enqueue_style(
			Mesh_Product_Table_Assets::STYLE_HANDLE
		);

		wp_enqueue_script(
			Mesh_Product_Table_Assets::SCRIPT_HANDLE
		);

		return $this->renderer->render(
			$data
		);
	}
}
