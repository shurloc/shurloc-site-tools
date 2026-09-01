<?php
/**
 * Tests for the catalog analyzer.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Analyzers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Specification;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;
use Shurloc\SiteTools\Product\Reports\Catalog_Report;

/**
 * Tests catalog variation analysis.
 */
final class CatalogAnalyzerTest extends TestCase {

	/**
	 * Catalog analyzer.
	 *
	 * @var Catalog_Analyzer
	 */
	private Catalog_Analyzer $analyzer;


	/**
	 * Set up the catalog analyzer.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->analyzer = new Catalog_Analyzer(
			mesh_parser: new Mesh_Parser(),
		);
	}


	/**
	 * Analyze returns a catalog report.
	 *
	 * @return void
	 */
	public function test_analyze_returns_catalog_report(): void {

		$report = $this->analyzer->analyze(
			entries: array(),
		);

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertInstanceOf(
			Catalog_Report::class,
			$report
		);
	}


	/**
	 * Recognized mesh variations are added to the report.
	 *
	 * @return void
	 */
	public function test_recognized_mesh_variations_are_reported(): void {

		$entry = $this->create_variation_entry(
			variation: '110/80 Yellow $20.00',
			price: 20.00,
		);

		$report = $this->analyzer->analyze(
			entries: array( $entry ),
		);

		$recognized = $report->get_recognized_specifications();

		$this->assertCount(
			1,
			$recognized
		);

		$this->assertCount(
			0,
			$report->get_unrecognized_variations()
		);

		$this->assertCount(
			0,
			$report->get_invalid_specifications()
		);

		$this->assertSame(
			'110/80 Yellow $20.00',
			$recognized[0]['variation']
		);

		$this->assertInstanceOf(
			Mesh_Specification::class,
			$recognized[0]['spec']
		);

		$this->assertTrue(
			$recognized[0]['spec']->is_valid()
		);
	}


	/**
	 * Recognized variations preserve catalog metadata.
	 *
	 * @return void
	 */
	public function test_recognized_variations_preserve_catalog_metadata(): void {

		$entry = $this->create_variation_entry(
			variation: '110/80 Yellow $20.00',
			price: 20.00,
			product_id: 123,
			product_name: 'Test Mesh Product',
			edit_url: 'https://example.com/edit/123',
		);

		$report = $this->analyzer->analyze(
			entries: array( $entry ),
		);

		$recognized = $report->get_recognized_specifications()[0];

		$this->assertSame(
			123,
			$recognized['product_id']
		);

		$this->assertSame(
			'Test Mesh Product',
			$recognized['product_name']
		);

		$this->assertSame(
			20.00,
			$recognized['price']
		);

		$this->assertSame(
			'https://example.com/edit/123',
			$recognized['edit_url']
		);
	}


	/**
	 * Unrecognized variations are added to the report.
	 *
	 * @return void
	 */
	public function test_unrecognized_variations_are_reported(): void {

		$entry = $this->create_variation_entry(
			variation: 'Custom Mesh Option',
			price: 25.00,
		);

		$report = $this->analyzer->analyze(
			entries: array( $entry ),
		);

		$unrecognized = $report->get_unrecognized_variations();

		$this->assertCount(
			0,
			$report->get_recognized_specifications()
		);

		$this->assertCount(
			1,
			$unrecognized
		);

		$this->assertCount(
			0,
			$report->get_invalid_specifications()
		);

		$this->assertSame(
			'Custom Mesh Option',
			$unrecognized[0]['variation']
		);
	}


	/**
	 * Unrecognized variations preserve catalog metadata.
	 *
	 * @return void
	 */
	public function test_unrecognized_variations_preserve_catalog_metadata(): void {

		$entry = $this->create_variation_entry(
			variation: 'Custom Mesh Option',
			price: 25.00,
			product_id: 123,
			product_name: 'Test Product',
			edit_url: 'https://example.com/edit/123',
		);

		$report = $this->analyzer->analyze(
			entries: array( $entry ),
		);

		$unrecognized = $report->get_unrecognized_variations()[0];

		$this->assertSame(
			'Custom Mesh Option',
			$unrecognized['variation']
		);

		$this->assertSame(
			25.00,
			$unrecognized['price']
		);

		$this->assertSame(
			123,
			$unrecognized['product_id']
		);

		$this->assertSame(
			'Test Product',
			$unrecognized['product_name']
		);

		$this->assertSame(
			'https://example.com/edit/123',
			$unrecognized['edit_url']
		);
	}


	/**
	 * Recognized invalid specifications are reported as invalid.
	 *
	 * @return void
	 */
	public function test_invalid_mesh_specifications_are_reported(): void {

		$entry = $this->create_variation_entry(
			variation: '350/30 Orange $35.00',
			price: 35.00,
		);

		$report = $this->analyzer->analyze(
			entries: array( $entry ),
		);

		$invalid = $report->get_invalid_specifications();

		$this->assertCount(
			1,
			$report->get_recognized_specifications()
		);

		$this->assertCount(
			1,
			$invalid
		);

		$this->assertSame(
			'350/30 Orange $35.00',
			$invalid[0]['variation']
		);

		$this->assertInstanceOf(
			Mesh_Specification::class,
			$invalid[0]['spec']
		);

		$this->assertFalse(
			$invalid[0]['spec']->is_valid()
		);

		$this->assertSame(
			array( 'Orange' ),
			$invalid[0]['spec']->get_unknown_tokens()
		);
	}


	/**
	 * Invalid specifications preserve catalog metadata.
	 *
	 * @return void
	 */
	public function test_invalid_specifications_preserve_catalog_metadata(): void {

		$entry = $this->create_variation_entry(
			variation: '350/30 Orange $35.00',
			price: 35.00,
			product_id: 789,
			product_name: 'Invalid Mesh Product',
			edit_url: 'https://example.com/edit/789',
		);

		$report = $this->analyzer->analyze(
			entries: array( $entry ),
		);

		$invalid = $report->get_invalid_specifications()[0];

		$this->assertSame(
			789,
			$invalid['product_id']
		);

		$this->assertSame(
			'Invalid Mesh Product',
			$invalid['product_name']
		);

		$this->assertSame(
			35.00,
			$invalid['price']
		);

		$this->assertSame(
			'https://example.com/edit/789',
			$invalid['edit_url']
		);
	}


	/**
	 * Invalid specifications are not reported as unrecognized.
	 *
	 * @return void
	 */
	public function test_invalid_specifications_are_not_reported_as_unrecognized(): void {

		$entry = $this->create_variation_entry(
			variation: '350/30 Orange $35.00',
			price: 35.00,
		);

		$report = $this->analyzer->analyze(
			entries: array( $entry ),
		);

		$this->assertCount(
			1,
			$report->get_invalid_specifications()
		);

		$this->assertCount(
			0,
			$report->get_unrecognized_variations()
		);
	}


	/**
	 * Mixed variations are classified into the correct report collections.
	 *
	 * @return void
	 */
	public function test_mixed_variations_are_classified_correctly(): void {

		$entries = array(
			$this->create_variation_entry(
				variation: '110/80 Yellow $20.00',
				price: 20.00,
			),
			$this->create_variation_entry(
				variation: '350/30 Orange $35.00',
				price: 35.00,
			),
			$this->create_variation_entry(
				variation: 'Custom Mesh Option',
				price: 25.00,
			),
		);

		$report = $this->analyzer->analyze(
			entries: $entries,
		);

		$this->assertCount(
			2,
			$report->get_recognized_specifications()
		);

		$this->assertCount(
			1,
			$report->get_invalid_specifications()
		);

		$this->assertCount(
			1,
			$report->get_unrecognized_variations()
		);

		$this->assertSame(
			3,
			$report->total_variations()
		);
	}


	/**
	 * Empty entry collections produce an empty report.
	 *
	 * @return void
	 */
	public function test_empty_entry_collection_produces_empty_report(): void {

		$report = $this->analyzer->analyze(
			entries: array(),
		);

		$this->assertSame(
			0,
			$report->total_variations()
		);

		$this->assertCount(
			0,
			$report->get_recognized_specifications()
		);

		$this->assertCount(
			0,
			$report->get_unrecognized_variations()
		);

		$this->assertCount(
			0,
			$report->get_invalid_specifications()
		);
	}


	/**
	 * Verify constructor signature.
	 *
	 * @return void
	 */
	public function test_constructor_signature(): void {

		$reflection = new ReflectionMethod(
			Catalog_Analyzer::class,
			'__construct'
		);

		$parameters = $reflection->getParameters();

		self::assertSame(
			array(
				'mesh_parser' => Mesh_Parser::class,
			),
			array_reduce(
				$parameters,
				static function (
					array $signature,
					ReflectionParameter $parameter
				): array {

					$type = $parameter->getType();

					$signature[ $parameter->getName() ] =
					$type instanceof ReflectionNamedType
						? $type->getName()
						: '';

					return $signature;
				},
				array()
			)
		);
	}


	/**
	 * Verify that analyze preserves its named parameter.
	 *
	 * @return void
	 */
	public function test_analyze_uses_entries_parameter_name(): void {

		$reflection = new ReflectionMethod(
			Catalog_Analyzer::class,
			'analyze'
		);

		$parameters = $reflection->getParameters();

		self::assertNotEmpty(
			$parameters
		);

		self::assertSame(
			'entries',
			$parameters[0]->getName()
		);
	}


	/**
	 * Create a catalog variation entry fixture.
	 *
	 * @param string     $variation    Variation value.
	 * @param float|null $price        Variation price.
	 * @param int        $product_id   Product ID.
	 * @param string     $product_name Product name.
	 * @param string     $edit_url     Product edit URL.
	 * @return Catalog_Variation_Entry
	 */
	private function create_variation_entry(
		string $variation,
		?float $price,
		int $product_id = 123,
		string $product_name = 'Test Product',
		string $edit_url = ''
	): Catalog_Variation_Entry {

		return new Catalog_Variation_Entry(
			variation: $variation,
			price: $price,
			product_id: $product_id,
			product_name: $product_name,
			edit_url: $edit_url,
		);
	}
}
