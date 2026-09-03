<?php
/**
 * Tests for the catalog analysis report.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Reports;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Models\Mesh_Specification;

/**
 * Catalog analysis report tests.
 */
final class CatalogReportTest extends TestCase {

	/**
	 * Verify a new report is empty.
	 *
	 * @return void
	 */
	public function test_new_report_is_empty(): void {

		$report = new Catalog_Report();

		self::assertSame( array(), $report->get_recognized_specifications() );
		self::assertSame( array(), $report->get_unrecognized_variations() );
		self::assertSame( array(), $report->get_invalid_specifications() );
		self::assertSame( 0, $report->total_variations() );
		self::assertSame( 0, $report->recognized_specification_count() );
		self::assertSame( 0, $report->unrecognized_variation_count() );
		self::assertSame( 0, $report->invalid_specification_count() );
	}

	/**
	 * Verify recognized specifications and metadata are stored.
	 *
	 * @return void
	 */
	public function test_add_recognized_specification_stores_entry(): void {

		$report = new Catalog_Report();
		$spec   = $this->create_spec();

		$report->add_recognized_specification(
			variation: '110/80 Yellow $20.00',
			spec: $spec,
			metadata: array(
				'product_id' => 123,
			),
		);

		self::assertSame(
			array(
				array(
					'product_id' => 123,
					'variation'  => '110/80 Yellow $20.00',
					'spec'       => $spec,
				),
			),
			$report->get_recognized_specifications()
		);
	}

	/**
	 * Verify unrecognized variations and metadata are stored.
	 *
	 * @return void
	 */
	public function test_add_unrecognized_variation_stores_entry(): void {

		$report = new Catalog_Report();

		$report->add_unrecognized_variation(
			variation: 'Premium Orange',
			metadata: array(
				'product_id' => 123,
			),
		);

		self::assertSame(
			array(
				array(
					'product_id' => 123,
					'variation'  => 'Premium Orange',
				),
			),
			$report->get_unrecognized_variations()
		);
	}

	/**
	 * Verify invalid specifications and metadata are stored.
	 *
	 * @return void
	 */
	public function test_add_invalid_specification_stores_entry(): void {

		$report = new Catalog_Report();
		$spec   = $this->create_spec(
			color: null,
		);

		$report->add_invalid_specification(
			variation: '110/80 Orange $20.00',
			spec: $spec,
			metadata: array(
				'product_id' => 123,
			),
		);

		self::assertSame(
			array(
				array(
					'product_id' => 123,
					'variation'  => '110/80 Orange $20.00',
					'spec'       => $spec,
				),
			),
			$report->get_invalid_specifications()
		);
	}

	/**
	 * Verify report fields take precedence over conflicting metadata.
	 *
	 * @return void
	 */
	public function test_report_fields_override_conflicting_metadata(): void {

		$report        = new Catalog_Report();
		$spec          = $this->create_spec();
		$metadata_spec = $this->create_spec(
			color: 'White',
		);

		$report->add_recognized_specification(
			variation: '110/80 Yellow $20.00',
			spec: $spec,
			metadata: array(
				'variation' => 'Metadata variation',
				'spec'      => $metadata_spec,
			),
		);

		$entry = $report->get_recognized_specifications()[0];

		self::assertSame( '110/80 Yellow $20.00', $entry['variation'] );
		self::assertSame( $spec, $entry['spec'] );
	}

	/**
	 * Verify counts and summary reflect all report collections.
	 *
	 * @return void
	 */
	public function test_counts_and_summary_are_calculated(): void {

		$report = new Catalog_Report();
		$spec   = $this->create_spec();

		$report->add_recognized_specification( 'Recognized', $spec );
		$report->add_unrecognized_variation( 'Unrecognized' );
		$report->add_invalid_specification( 'Invalid', $spec );

		self::assertSame( 2, $report->total_variations() );
		self::assertSame( 1, $report->recognized_specification_count() );
		self::assertSame( 1, $report->unrecognized_variation_count() );
		self::assertSame( 1, $report->invalid_specification_count() );
		self::assertSame(
			array(
				'total_variations'          => 2,
				'recognized_specifications' => 1,
				'unrecognized_variations'   => 1,
				'invalid_specifications'    => 1,
			),
			$report->summary()
		);
	}

	/**
	 * Verify serialization converts specification objects to arrays.
	 *
	 * @return void
	 */
	public function test_to_array_serializes_specifications(): void {

		$report = new Catalog_Report();
		$spec   = $this->create_spec();

		$report->add_recognized_specification( 'Recognized', $spec );
		$report->add_unrecognized_variation( 'Unrecognized' );
		$report->add_invalid_specification( 'Invalid', $spec );

		$serialized = $report->to_array();

		self::assertSame( $report->summary(), $serialized['summary'] );
		self::assertSame( $spec->to_array(), $serialized['recognized_specifications'][0]['spec'] );
		self::assertSame( 'Unrecognized', $serialized['unrecognized_variations'][0]['variation'] );
		self::assertSame( $spec->to_array(), $serialized['invalid_specifications'][0]['spec'] );
	}

	/**
	 * Create a mesh specification fixture.
	 *
	 * @param string|null $color Mesh color.
	 * @return Mesh_Specification
	 */
	private function create_spec(
		?string $color = 'Yellow'
	): Mesh_Specification {

		return new Mesh_Specification(
			raw: '110/80 Yellow $20.00',
			mesh_count: 110,
			thread_diameter: 80,
			modifier: null,
			color: $color,
			pack_size: null,
			price_text: '$20.00',
			recognized: true,
			unknown_tokens: array(),
		);
	}
}
