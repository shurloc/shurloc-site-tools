<?php
/**
 * Tests for the mesh parser.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Parsers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Shurloc\SiteTools\Product\Models\Mesh_Specification;

/**
 * Mesh parser tests.
 */
class MeshParserTest extends TestCase {

	/**
	 * Mesh parser under test.
	 *
	 * @var Mesh_Parser
	 */
	private Mesh_Parser $parser;


	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->parser = new Mesh_Parser();
	}

	/**
	 * Verify the raw input string is preserved.
	 */
	public function test_raw_string_is_preserved(): void {

		$spec = $this->parser->parse(
			text: '110/80 Yellow $23.75',
		);

		$this->assertSame(
			'110/80 Yellow $23.75',
			$spec->get_raw()
		);
	}


	/**
	 * Verify that standard mesh specifications are parsed correctly.
	 *
	 * @param string             $input    The raw variation string.
	 * @param Mesh_Specification $expected The expected spec after parsing.
	 */
	#[DataProviderExternal(
		MeshParserDataProvider::class,
		'standard_mesh',
	)]
	public function test_parses_standard_mesh(
		string $input,
		Mesh_Specification $expected,
	): void {

		$actual = $this->parser->parse(
			text: $input,
		);

		$this->assertTrue(
			$actual->equals(
				$expected
			)
		);
	}


	/**
	 * Verify that standard mesh specifications are recognized correctly.
	 *
	 * @param string $variation The raw variation string.
	 */
	#[DataProviderExternal(
		MeshParserDataProvider::class,
		'recognized_mesh',
	)]
	public function test_recognizes_mesh_specifications(
		string $variation,
	): void {

		$spec = $this->parser->parse(
			text: $variation,
		);

		$this->assertTrue(
			$spec->is_recognized()
		);
	}


	/**
	 * Verify that non-standard specifications are unrecognized correctly.
	 *
	 * @param string $variation The raw variation string.
	 */
	#[DataProviderExternal(
		MeshParserDataProvider::class,
		'unrecognized_variations',
	)]
	public function test_rejects_non_mesh_specifications(
		string $variation,
	): void {

		$spec = $this->parser->parse(
			text: $variation,
		);

		$this->assertFalse(
			$spec->is_recognized()
		);
	}


	/**
	 * Verify that prices are extracted correctly.
	 *
	 * @param string $variation      The raw variation string.
	 * @param string $expected_price The expected extracted price text.
	 */
	#[DataProviderExternal(
		MeshParserDataProvider::class,
		'prices',
	)]
	public function test_extracts_prices(
		string $variation,
		string $expected_price,
	): void {

		$spec = $this->parser->parse(
			text: $variation,
		);

		$this->assertSame(
			$expected_price,
			$spec->get_price_text()
		);
	}


	/**
	 * Verify that colors are extracted correctly.
	 *
	 * @param string $variation      The raw variation string.
	 * @param string $expected_color The expected extracted color text.
	 */
	#[DataProviderExternal(
		MeshParserDataProvider::class,
		'colors',
	)]
	public function test_extracts_colors(
		string $variation,
		string $expected_color,
	): void {

		$spec = $this->parser->parse(
			text: $variation,
		);

		$this->assertSame(
			$expected_color,
			$spec->get_color()
		);
	}


	/**
	 * Invalid mesh specifications should still be recognized.
	 */
	public function test_invalid_mesh_values_are_recognized(): void {

		$spec = $this->parser->parse(
			text: '350/30 Orange $35.00',
		);

		$this->assertTrue(
			$spec->is_recognized()
		);

		$this->assertFalse(
			$spec->is_valid()
		);

		$this->assertSame(
			350,
			$spec->get_mesh_count()
		);

		$this->assertSame(
			30,
			$spec->get_thread_diameter()
		);
	}


	/**
	 * Mesh suffixes should not prevent recognition.
	 *
	 * @param string $variation The raw variation string.
	 */
	#[DataProviderExternal(
		MeshParserDataProvider::class,
		'suffix_variations',
	)]
	public function test_recognizes_mesh_suffix_variations(
		string $variation,
	): void {

		$spec = $this->parser->parse(
			text: $variation,
		);

		$this->assertTrue(
			$spec->is_recognized()
		);
	}
}
