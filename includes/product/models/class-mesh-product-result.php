<?php
/**
 * Mesh product analysis result.
 *
 * Represents the result of analyzing a WooCommerce product for mesh
 * variations.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Models;

/**
 * Mesh product analysis result.
 */
final class Mesh_Product_Result {

	/**
	 * Recognized mesh variations.
	 *
	 * @var array<int, array{
	 *     entry: Catalog_Variation_Entry,
	 *     spec: Mesh_Specification
	 * }>
	 */
	public array $mesh_variations = array();

	/**
	 * Ignored variations.
	 *
	 * Examples:
	 * - Thin Thread separators.
	 * - Variations with no price.
	 *
	 * @var Catalog_Variation_Entry[]
	 */
	public array $ignored_variations = array();

	/**
	 * Unrecognized paid variations.
	 *
	 * These require attention because they are not recognized as mesh
	 * specifications but appear to be purchasable variations.
	 *
	 * @var Catalog_Variation_Entry[]
	 */
	public array $unrecognized_variations = array();

	/**
	 * Add a recognized mesh variation.
	 *
	 * @param Catalog_Variation_Entry $entry Catalog variation entry.
	 * @param Mesh_Specification      $spec Parsed mesh specification.
	 * @return void
	 */
	public function add_mesh_variation(
		Catalog_Variation_Entry $entry,
		Mesh_Specification $spec
	): void {

		$this->mesh_variations[] = array(
			'entry' => $entry,
			'spec'  => $spec,
		);
	}

	/**
	 * Add an ignored variation.
	 *
	 * @param Catalog_Variation_Entry $entry Catalog variation entry.
	 * @return void
	 */
	public function add_ignored_variation(
		Catalog_Variation_Entry $entry
	): void {

		$this->ignored_variations[] = $entry;
	}

	/**
	 * Add an unrecognized variation.
	 *
	 * @param Catalog_Variation_Entry $entry Catalog variation entry.
	 * @return void
	 */
	public function add_unrecognized_variation(
		Catalog_Variation_Entry $entry
	): void {

		$this->unrecognized_variations[] = $entry;
	}

	/**
	 * Return recognized mesh variations.
	 *
	 * Each variation contains the original catalog entry and the
	 * successfully parsed mesh specification.
	 *
	 * @return array<int, array{
	 *     entry: Catalog_Variation_Entry,
	 *     spec: Mesh_Specification
	 * }>
	 */
	public function get_mesh_variations(): array {

		return $this->mesh_variations;
	}

	/**
	 * Return ignored variations.
	 *
	 * Ignored variations are entries that are intentionally excluded from
	 * mesh analysis results, such as non-purchasable separators or
	 * variations without assigned pricing.
	 *
	 * @return Catalog_Variation_Entry[]
	 */
	public function get_ignored_variations(): array {

		return $this->ignored_variations;
	}

	/**
	 * Return unrecognized paid variations.
	 *
	 * These are purchasable catalog variations that were not recognized as
	 * valid mesh specifications and may require review.
	 *
	 * @return Catalog_Variation_Entry[]
	 */
	public function get_unrecognized_variations(): array {

		return $this->unrecognized_variations;
	}

	/**
	 * Determine whether this represents a mesh product.
	 *
	 * @return bool True if mesh variations were found.
	 */
	public function is_mesh_product(): bool {

		return ! empty( $this->mesh_variations );
	}

	/**
	 * Return the number of mesh variations.
	 *
	 * @return int Number of recognized mesh variations.
	 */
	public function mesh_variation_count(): int {

		return count(
			$this->mesh_variations
		);
	}
}
