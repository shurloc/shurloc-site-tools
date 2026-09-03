<?php
/**
 * Product schema service test double.
 *
 * Provides a controllable implementation of the product schema service
 * interface for testing consumers that generate product schema data.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;

/**
 * Product schema service test double.
 */
final class Product_Schema_Service_Double implements Product_Schema_Service_Interface {

	/**
	 * Schema returned by the double.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $schema;

	/**
	 * Calls to generate().
	 *
	 * @var Catalog_Product_Entry[]
	 */
	private array $calls = array();

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed>|null $schema Schema to return.
	 */
	public function __construct(
		?array $schema = null
	) {

		$this->schema = $schema;
	}

	/**
	 * Generate product schema.
	 *
	 * Records the supplied product and returns the configured schema.
	 *
	 * @param Catalog_Product_Entry $product Catalog product.
	 * @return array<string,mixed>|null Product schema or null.
	 */
	public function generate(
		Catalog_Product_Entry $product
	): ?array {

		$this->calls[] = $product;

		return $this->schema;
	}

	/**
	 * Get calls to generate().
	 *
	 * @return Catalog_Product_Entry[] Products passed to generate().
	 */
	public function get_calls(): array {

		return $this->calls;
	}
}
