<?php
/**
 * Catalog analyzer.
 *
 * Analyzes a collection of WooCommerce variation names using the mesh parser.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Analyzers;

use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;
use Shurloc\SiteTools\Product\Reports\Catalog_Report;

/**
 * Catalog analyzer.
 */
final class Catalog_Analyzer {

	/**
	 * Mesh parser.
	 *
	 * @var Mesh_Parser
	 */
	private Mesh_Parser $mesh_parser;

	/**
	 * Constructor.
	 *
	 * @param Mesh_Parser $mesh_parser Mesh parser.
	 */
	public function __construct(
		Mesh_Parser $mesh_parser
	) {

		$this->mesh_parser = $mesh_parser;
	}

	/**
	 * Analyze a collection of catalog variation entries.
	 *
	 * Returns three collections:
	 *
	 * - recognized mesh specifications
	 * - unrecognized variations
	 * - recognized but invalid mesh specifications
	 *
	 * @param Catalog_Variation_Entry[] $entries Catalog variation entries.
	 * @return Catalog_Report
	 */
	public function analyze(
		array $entries
	): Catalog_Report {

		$report = new Catalog_Report();

		foreach ( $entries as $entry ) {

			$spec = $this->mesh_parser->parse(
				$entry->variation
			);

			$metadata = $entry->to_array();

			if ( ! $spec->is_recognized() ) {

				$report->add_unrecognized_variation(
					$entry->variation,
					$metadata
				);

				continue;
			}

			$report->add_recognized_specification(
				$entry->variation,
				$spec,
				$metadata
			);

			if ( ! $spec->is_valid() ) {

				$report->add_invalid_specification(
					$entry->variation,
					$spec,
					$metadata
				);
			}
		}

		return $report;
	}
}
