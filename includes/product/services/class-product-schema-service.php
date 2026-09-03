<?php
/**
 * Product schema service.
 *
 * Coordinates product schema generation and mesh product enrichment.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Generators\Product_Schema_Generator;
use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;

/**
 * Product schema service.
 */
final class Product_Schema_Service implements Product_Schema_Service_Interface {

	/**
	 * Schema generator.
	 *
	 * @var Product_Schema_Generator
	 */
	private Product_Schema_Generator $generator;

	/**
	 * Mesh product schema service.
	 *
	 * @var Mesh_Product_Schema_Service_Interface
	 */
	private Mesh_Product_Schema_Service_Interface $mesh_schema_service;

	/**
	 * Constructor.
	 *
	 * @param Product_Schema_Generator              $generator           Schema generator.
	 * @param Mesh_Product_Schema_Service_Interface $mesh_schema_service Mesh schema service.
	 */
	public function __construct(
		Product_Schema_Generator $generator,
		Mesh_Product_Schema_Service_Interface $mesh_schema_service
	) {

		$this->generator           = $generator;
		$this->mesh_schema_service = $mesh_schema_service;
	}

	/**
	 * Generate schema for a catalog product.
	 *
	 * Returns base product schema for all products and enriches mesh products
	 * with aggregate offers.
	 *
	 * @param Catalog_Product_Entry $product Catalog product.
	 * @return array<string,mixed>
	 */
	public function generate(
		Catalog_Product_Entry $product
	): array {

		$result = $this->mesh_schema_service->analyze(
			$product
		);

		if ( null === $result ) {

			$result = new Mesh_Product_Result();
		}

		return $this->generator->generate(
			$product,
			$result
		);
	}
}
