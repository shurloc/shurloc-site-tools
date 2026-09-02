<?php
/**
 * Test double for mesh product schema service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;

/**
 * Configurable mesh product schema service test double.
 */
final class Mesh_Product_Schema_Service_Double implements Mesh_Product_Schema_Service_Interface {

	/**
	 * Analysis result returned by the double.
	 *
	 * @var Mesh_Product_Result|null
	 */
	private ?Mesh_Product_Result $result;

	/**
	 * Products passed to analyze().
	 *
	 * @var Catalog_Product_Entry[]
	 */
	private array $calls = array();

	/**
	 * Create the test double.
	 *
	 * @param Mesh_Product_Result|null $result Analysis result.
	 */
	public function __construct(
		?Mesh_Product_Result $result = null
	) {

		$this->result = $result;
	}

	/**
	 * Analyze a catalog product.
	 *
	 * @param Catalog_Product_Entry $product Catalog product.
	 * @return Mesh_Product_Result|null
	 */
	public function analyze(
		Catalog_Product_Entry $product
	): ?Mesh_Product_Result {

		$this->calls[] = $product;

		return $this->result;
	}

	/**
	 * Get products passed to analyze().
	 *
	 * @return Catalog_Product_Entry[]
	 */
	public function get_calls(): array {

		return $this->calls;
	}
}
