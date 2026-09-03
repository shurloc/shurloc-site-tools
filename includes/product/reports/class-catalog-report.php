<?php
/**
 * Catalog analysis report.
 *
 * Stores the results of analyzing a collection of WooCommerce variation
 * names.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Reports;

use Shurloc\SiteTools\Product\Models\Mesh_Specification;

/**
 * Catalog analysis report.
 */
final class Catalog_Report {

	/**
	 * Recognized mesh specifications.
	 *
	 * Each entry contains the original variation name and its parsed
	 * specification.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $recognized_specifications = array();

	/**
	 * Unrecognized variation names.
	 *
	 * These variation names were not identified as mesh specifications.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $unrecognized_variations = array();

	/**
	 * Invalid mesh specifications.
	 *
	 * These variation names were recognized as mesh specifications but did
	 * not parse into a valid specification.
	 *
	 * Each entry contains the original variation name and its parsed
	 * specification.
	 *
	 * @var array<int, array<string,mixed>>
	 */
	public array $invalid_specifications = array();

	/**
	 * Add a recognized mesh specification.
	 *
	 * @param string               $variation Variation name.
	 * @param Mesh_Specification   $spec      Parsed specification.
	 * @param array<string, mixed> $metadata Optional report metadata.
	 * @return void
	 */
	public function add_recognized_specification(
		string $variation,
		Mesh_Specification $spec,
		array $metadata = array()
	): void {

		$this->recognized_specifications[] = array_merge(
			$metadata,
			array(
				'variation' => $variation,
				'spec'      => $spec,
			)
		);
	}

	/**
	 * Add an unrecognized variation name.
	 *
	 * @param string               $variation Variation name.
	 * @param array<string, mixed> $metadata Optional report metadata.
	 * @return void
	 */
	public function add_unrecognized_variation(
		string $variation,
		array $metadata = array()
	): void {

		$this->unrecognized_variations[] = array_merge(
			$metadata,
			array(
				'variation' => $variation,
			)
		);
	}

	/**
	 * Add an invalid mesh specification.
	 *
	 * @param string               $variation Variation name.
	 * @param Mesh_Specification   $spec      Parsed specification.
	 * @param array<string, mixed> $metadata Optional report metadata.
	 * @return void
	 */
	public function add_invalid_specification(
		string $variation,
		Mesh_Specification $spec,
		array $metadata = array()
	): void {

		$this->invalid_specifications[] = array_merge(
			$metadata,
			array(
				'variation' => $variation,
				'spec'      => $spec,
			)
		);
	}

	/**
	 * Return the recognized mesh specifications.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recognized_specifications(): array {

		return $this->recognized_specifications;
	}

	/**
	 * Return the unrecognized variation names.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_unrecognized_variations(): array {

		return $this->unrecognized_variations;
	}

	/**
	 * Return the invalid mesh specifications.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_invalid_specifications(): array {

		return $this->invalid_specifications;
	}

	/**
	 * Return the total number of variations analyzed.
	 *
	 * @return int
	 */
	public function total_variations(): int {

		return (
			$this->recognized_specification_count() +
			$this->unrecognized_variation_count()
		);
	}

	/**
	 * Return the number of recognized mesh specifications.
	 *
	 * @return int
	 */
	public function recognized_specification_count(): int {

		return count( $this->recognized_specifications );
	}

	/**
	 * Return the number of unrecognized variation names.
	 *
	 * @return int
	 */
	public function unrecognized_variation_count(): int {

		return count( $this->unrecognized_variations );
	}

	/**
	 * Return the number of invalid mesh specifications.
	 *
	 * @return int
	 */
	public function invalid_specification_count(): int {

		return count( $this->invalid_specifications );
	}

	/**
	 * Return a summary of the catalog analysis.
	 *
	 * @return array{
	 *     total_variations: int,
	 *     recognized_specifications: int,
	 *     unrecognized_variations: int,
	 *     invalid_specifications: int
	 * }
	 */
	public function summary(): array {

		return array(
			'total_variations'          => $this->total_variations(),
			'recognized_specifications' => $this->recognized_specification_count(),
			'unrecognized_variations'   => $this->unrecognized_variation_count(),
			'invalid_specifications'    => $this->invalid_specification_count(),
		);
	}

	/**
	 * Return the report as an associative array.
	 *
	 * @return array{
	 *     summary: array{
	 *         total_variations: int,
	 *         recognized_specifications: int,
	 *         unrecognized_variations: int,
	 *         invalid_specifications: int
	 *     },
	 *     recognized_specifications: array<int, array<string, mixed>>,
	 *     unrecognized_variations: array<int, array<string, mixed>>,
	 *     invalid_specifications: array<int, array<string, mixed>>
	 * }
	 */
	public function to_array(): array {

		$recognized = array();

		foreach ( $this->recognized_specifications as $entry ) {

			$item = $entry;

			$item['spec'] = $item['spec']->to_array();

			$recognized[] = $item;
		}

		$invalid = array();

		foreach ( $this->invalid_specifications as $entry ) {

			$item = $entry;

			$item['spec'] = $item['spec']->to_array();

			$invalid[] = $item;
		}

		return array(
			'summary'                   => $this->summary(),
			'recognized_specifications' => $recognized,
			'unrecognized_variations'   => $this->unrecognized_variations,
			'invalid_specifications'    => $invalid,
		);
	}
}
