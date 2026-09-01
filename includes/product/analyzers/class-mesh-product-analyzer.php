<?php
/**
 * Mesh product analyzer.
 *
 * Analyzes WooCommerce product variation entries to determine whether a
 * product contains mesh specifications.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Analyzers;

use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;

/**
 * Mesh product analyzer.
 */
final class Mesh_Product_Analyzer implements Mesh_Product_Analyzer_Interface {

	/**
	 * Mesh parser.
	 *
	 * @var Mesh_Parser
	 */
	private Mesh_Parser $parser;

	/**
	 * Constructor.
	 *
	 * @param Mesh_Parser $parser Mesh parser.
	 */
	public function __construct(
		Mesh_Parser $parser
	) {

		$this->parser = $parser;
	}

	/**
	 * Analyze a collection of catalog variation entries.
	 *
	 * A product is considered a mesh product if at least one variation
	 * contains a recognized mesh specification.
	 *
	 * Variations that are not recognized and have no price (or a zero price)
	 * are ignored. These represent non-purchasable separators such as
	 * "Thin Thread".
	 *
	 * @param Catalog_Variation_Entry[] $entries Product variations.
	 * @return Mesh_Product_Result
	 */
	public function analyze(
		array $entries
	): Mesh_Product_Result {

		$result = new Mesh_Product_Result();

		foreach ( $entries as $entry ) {

			$spec = $this->parser->parse(
				$entry->variation
			);

			if ( $spec->is_recognized() ) {

				$result->add_mesh_variation(
					$entry,
					$spec
				);

				continue;
			}

			if (
				null === $entry->price ||
				0.0 === $entry->price
			) {
				$result->add_ignored_variation(
					$entry
				);

				continue;
			}

			$result->add_unrecognized_variation(
				$entry
			);
		}

		return $result;
	}
}
