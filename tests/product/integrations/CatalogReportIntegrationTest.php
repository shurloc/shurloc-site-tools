<?php
/**
 * Tests catalog report generation.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shurloc\SiteTools\Product\Analyzers\Catalog_Analyzer;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;
use Shurloc\SiteTools\Product\Reports\Catalog_Report;

/**
 * Tests catalog report generation.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class CatalogReportIntegrationTest extends TestCase {

	/**
	 * Catalog analyzer.
	 *
	 * @var Catalog_Analyzer
	 */
	private Catalog_Analyzer $analyzer;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->analyzer = new Catalog_Analyzer(
			mesh_parser: new Mesh_Parser(),
		);
	}

	/**
	 * Test catalog report generation from fixture data.
	 *
	 * @throws JsonException    If the fixture JSON is invalid.
	 * @throws RuntimeException If the fixture cannot be read.
	 */
	public function test_generates_catalog_report(): void {

		$entries = MeshCatalogDataProvider::load_catalog();

		$report = $this->analyzer->analyze(
			entries: $entries,
		);

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertInstanceOf(
			Catalog_Report::class,
			$report
		);

		$data = $report->to_array();

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertArrayHasKey(
			'recognized_specifications',
			$data
		);

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertArrayHasKey(
			'unrecognized_variations',
			$data
		);

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertArrayHasKey(
			'invalid_specifications',
			$data
		);
	}

	/**
	 * Test catalog fixture produces entries.
	 *
	 * Ensures the fixture pipeline is working before analysis.
	 *
	 * @throws JsonException    If the fixture JSON is invalid.
	 * @throws RuntimeException If the fixture cannot be read.
	 */
	public function test_catalog_fixture_loads_entries(): void {

		$entries = MeshCatalogDataProvider::load_catalog();

		$this->assertNotEmpty(
			$entries
		);

		$this->assertContainsOnlyInstancesOf(
			Catalog_Variation_Entry::class,
			$entries,
		);
	}

	/**
	 * Test report recognizes known mesh specification.
	 *
	 * @throws JsonException    If the fixture JSON is invalid.
	 * @throws RuntimeException If the fixture cannot be read.
	 */
	public function test_report_identifies_known_mesh_specifications(): void {

		$entries = MeshCatalogDataProvider::load_catalog();

		$report = $this->analyzer->analyze(
			entries: $entries,
		);

		$data = $report->to_array();

		$recognized_specifications = array_column(
			$data['recognized_specifications'],
			'variation'
		);

		$this->assertContains(
			'123/70 Yellow $19.26',
			$recognized_specifications
		);
	}

	/**
	 * Test report identifies unknown catalog variations.
	 *
	 * @throws JsonException    If the fixture JSON is invalid.
	 * @throws RuntimeException If the fixture cannot be read.
	 */
	public function test_report_handles_unknown_variations(): void {

		$entries = MeshCatalogDataProvider::load_catalog();

		$entries[] = new Catalog_Variation_Entry(
			variation: 'Custom Promotional Product',
			price: null,
			product_id: 999999,
			product_name: 'Fixture Product',
			edit_url: '',
		);

		$report = $this->analyzer->analyze(
			entries: $entries,
		);

		$data = $report->to_array();

		$unrecognized_variations = array_column(
			$data['unrecognized_variations'],
			'variation'
		);

		$this->assertContains(
			'Custom Promotional Product',
			$unrecognized_variations
		);

		$this->assertNotContains(
			'Custom Promotional Product',
			$data['recognized_specifications']
		);
	}
}
