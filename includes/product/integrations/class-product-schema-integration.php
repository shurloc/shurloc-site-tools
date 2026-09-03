<?php
/**
 * Product schema integration.
 *
 * Registers product schema rendering hooks.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use Shurloc\SiteTools\Product\Renderers\Product_Schema_Renderer_Interface;
use Shurloc\SiteTools\Product\Services\Product_Catalog_Service_Interface;
use Shurloc\SiteTools\Product\Services\Product_Schema_Service_Interface;

/**
 * Product schema integration.
 */
final class Product_Schema_Integration {

	/**
	 * Catalog service.
	 *
	 * @var Product_Catalog_Service_Interface
	 */
	private Product_Catalog_Service_Interface $catalog_service;

	/**
	 * Product schema service.
	 *
	 * @var Product_Schema_Service_Interface
	 */
	private Product_Schema_Service_Interface $schema_service;

	/**
	 * Product schema renderer.
	 *
	 * @var Product_Schema_Renderer_Interface
	 */
	private Product_Schema_Renderer_Interface $renderer;

	/**
	 * Constructor.
	 *
	 * @param Product_Catalog_Service_Interface $catalog_service Catalog service.
	 * @param Product_Schema_Service_Interface  $schema_service  Product schema service.
	 * @param Product_Schema_Renderer_Interface $renderer        Product schema renderer.
	 */
	public function __construct(
		Product_Catalog_Service_Interface $catalog_service,
		Product_Schema_Service_Interface $schema_service,
		Product_Schema_Renderer_Interface $renderer
	) {

		$this->catalog_service = $catalog_service;
		$this->schema_service  = $schema_service;
		$this->renderer        = $renderer;
	}

	/**
	 * Register integration hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'wp_head',
			array(
				$this,
				'render_product_schema',
			),
			20
		);
	}

	/**
	 * Render product schema.
	 *
	 * @return void
	 */
	public function render_product_schema(): void {

		if ( ! is_product() ) {
			return;
		}

		$product = wc_get_product(
			get_the_ID()
		);

		if ( ! $product ) {
			return;
		}

		$entry = $this->catalog_service->get_product_entry(
			$product
		);

		if ( null === $entry ) {
			return;
		}

		$schema = $this->schema_service->generate(
			$entry
		);

		if ( null === $schema ) {
			return;
		}

		$this->renderer->render(
			$schema
		);
	}
}
