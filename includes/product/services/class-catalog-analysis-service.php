<?php
/**
 * Catalog analysis service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Analyzers\Catalog_Analyzer;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Reports\Catalog_Report;

/**
 * Collects and analyzes WooCommerce catalog variations.
 */
final class Catalog_Analysis_Service implements Catalog_Analysis_Service_Interface {

	/**
	 * Product catalog service.
	 *
	 * @var Product_Catalog_Service_Interface
	 */
	private Product_Catalog_Service_Interface $catalog_service;

	/**
	 * Catalog analyzer.
	 *
	 * @var Catalog_Analyzer
	 */
	private Catalog_Analyzer $catalog_analyzer;


	/**
	 * Constructor.
	 *
	 * @param Product_Catalog_Service_Interface $catalog_service Product catalog service.
	 * @param Catalog_Analyzer                  $catalog_analyzer        Catalog analyzer.
	 */
	public function __construct(
		Product_Catalog_Service_Interface $catalog_service,
		Catalog_Analyzer $catalog_analyzer
	) {

		$this->catalog_service  = $catalog_service;
		$this->catalog_analyzer = $catalog_analyzer;
	}


	/**
	 * Collect catalog variation entries.
	 *
	 * @return Catalog_Variation_Entry[]
	 */
	public function get_variation_entries(): array {

		$entries = array();

		$product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $product_ids as $product_id ) {

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$product_entries = $this->catalog_service->get_product_variation_entries(
				product: $product,
			);

			$entries = array_merge(
				$entries,
				$product_entries
			);
		}

		usort(
			$entries,
			static function (
				Catalog_Variation_Entry $left,
				Catalog_Variation_Entry $right
			): int {

				return strnatcasecmp(
					$left->variation,
					$right->variation
				);
			}
		);

		return $entries;
	}


	/**
	 * Collect catalog variation values.
	 *
	 * @return string[]
	 */
	public function get_variation_values(): array {

		return array_map(
			static function (
				Catalog_Variation_Entry $entry
			): string {

				return $entry->variation;
			},
			$this->get_variation_entries()
		);
	}


	/**
	 * Analyze the WooCommerce product catalog.
	 *
	 * @return Catalog_Report Catalog analysis report.
	 */
	public function analyze(): Catalog_Report {

		return $this->catalog_analyzer->analyze(
			entries: $this->get_variation_entries(),
		);
	}
}
