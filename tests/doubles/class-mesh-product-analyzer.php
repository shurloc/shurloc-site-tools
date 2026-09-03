<?php
/**
 * Mesh product analyzer test double.
 *
 * Provides a controllable implementation of the mesh product analyzer
 * interface for unit testing services that depend on mesh analysis.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Analyzers;

use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;

/**
 * Mesh product analyzer test double.
 */
final class Mesh_Product_Analyzer_Double implements Mesh_Product_Analyzer_Interface {

	/**
	 * Analysis result to return.
	 *
	 * @var Mesh_Product_Result
	 */
	private Mesh_Product_Result $result;

	/**
	 * Entries passed to analyze().
	 *
	 * @var Catalog_Variation_Entry[]
	 */
	private array $entries = array();

	/**
	 * Constructor.
	 *
	 * @param Mesh_Product_Result $result Analysis result to return.
	 */
	public function __construct(
		Mesh_Product_Result $result
	) {

		$this->result = $result;
	}

	/**
	 * Analyze catalog variation entries.
	 *
	 * Returns the predefined result supplied during construction.
	 * This allows dependent services to be tested without invoking the
	 * mesh parser or analyzer logic.
	 *
	 * @param Catalog_Variation_Entry[] $entries Catalog entries.
	 * @return Mesh_Product_Result Analysis result.
	 */
	public function analyze(
		array $entries
	): Mesh_Product_Result {

		$this->entries = $entries;

		return $this->result;
	}

	/**
	 * Return the entries passed to analyze().
	 *
	 * @return Catalog_Variation_Entry[]
	 */
	public function get_entries(): array {

		return $this->entries;
	}
}
