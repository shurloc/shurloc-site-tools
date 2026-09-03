<?php
/**
 * Integration tests for mesh recognition.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Analyzers\Catalog_Analyzer;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;
use Shurloc\SiteTools\Product\Reports\Catalog_Report;

/**
 * Integration tests using the exported WooCommerce catalog.
 */
final class MeshRecognitionTest extends TestCase {

	/**
	 * Analyze every catalog variation entry in the exported catalog.
	 *
	 * This test exercises the parser and analyzer against a real-world catalog
	 * snapshot.
	 */
	public function test_analyzes_catalog_fixture(): void {

		$catalog = MeshCatalogDataProvider::load_catalog();

		$analyzer = new Catalog_Analyzer(
			mesh_parser: new Mesh_Parser(),
		);

		$report = $analyzer->analyze( $catalog );

		$this->assertNotEmpty(
			$catalog,
			'Catalog fixture appears to be empty.'
		);

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertInstanceOf(
			Catalog_Report::class,
			$report,
			'The analyzer should return a catalog report.'
		);

		$this->assertSame(
			count( $catalog ),
			$report->total_variations(),
			'Every catalog variation should be classified.'
		);

		$this->assertGreaterThan(
			0,
			$report->recognized_specification_count(),
			'No catalog variations were recognized.'
		);

		$summary = $report->summary();

		$this->assertSame(
			$report->total_variations(),
			$summary['total_variations'],
			'The summary should report the correct total variation count.'
		);

		$this->assertSame(
			$report->recognized_specification_count(),
			$summary['recognized_specifications'],
			'The summary should report the recognized specification count.'
		);

		$this->assertSame(
			$report->unrecognized_variation_count(),
			$summary['unrecognized_variations'],
			'The summary should report the unrecognized variation count.'
		);

		$this->assertSame(
			$report->invalid_specification_count(),
			$summary['invalid_specifications'],
			'The summary should report the invalid specification count.'
		);

		$array = $report->to_array();

		$this->assertSame(
			$summary,
			$array['summary'],
			'The serialized report should include the computed summary.'
		);

		$this->assertCount(
			$report->recognized_specification_count(),
			$array['recognized_specifications'],
			'The serialized report should include every recognized specification.'
		);

		$this->assertCount(
			$report->unrecognized_variation_count(),
			$array['unrecognized_variations'],
			'The serialized report should include every unrecognized variation.'
		);

		$this->assertCount(
			$report->invalid_specification_count(),
			$array['invalid_specifications'],
			'The serialized report should include every invalid specification.'
		);
	}
}
