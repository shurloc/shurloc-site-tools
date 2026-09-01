<?php
/**
 * Tests for the mesh specification model.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Models;

use PHPUnit\Framework\TestCase;

/**
 * Mesh specification tests.
 */
final class MeshSpecificationTest extends TestCase {

	/**
	 * Verify that equals() returns true for identical specifications.
	 */
	public function test_equals_returns_true_for_identical_specs(): void {

		$spec_a = $this->create_spec();
		$spec_b = $this->create_spec();

		$this->assertTrue(
			$spec_a->equals( $spec_b )
		);
	}

	/**
	 * Verify that equals() returns false when the mesh count differs.
	 */
	public function test_equals_returns_false_for_different_mesh_count(): void {

		$spec_a = $this->create_spec();

		$spec_b = $this->create_spec(
			mesh_count: 160,
		);

		$this->assertFalse(
			$spec_a->equals( $spec_b )
		);
	}

	/**
	 * Verify that equals() returns false when the thread diameter differs.
	 */
	public function test_equals_returns_false_for_different_thread_diameter(): void {

		$spec_a = $this->create_spec();

		$spec_b = $this->create_spec(
			thread_diameter: 64,
		);

		$this->assertFalse(
			$spec_a->equals( $spec_b )
		);
	}

	/**
	 * Verify that equals() returns false when the modifier differs.
	 */
	public function test_equals_returns_false_for_different_modifier(): void {

		$spec_a = $this->create_spec();

		$spec_b = $this->create_spec(
			modifier: 'HD',
		);

		$this->assertFalse(
			$spec_a->equals( $spec_b )
		);
	}

	/**
	 * Verify that equals() returns false when the color differs.
	 */
	public function test_equals_returns_false_for_different_color(): void {

		$spec_a = $this->create_spec();

		$spec_b = $this->create_spec(
			color: 'White',
		);

		$this->assertFalse(
			$spec_a->equals( $spec_b )
		);
	}

	/**
	 * Verify that equals() returns false when the pack size differs.
	 */
	public function test_equals_returns_false_for_different_pack_size(): void {

		$spec_a = $this->create_spec();

		$spec_b = $this->create_spec(
			pack_size: '20 Pack',
		);

		$this->assertFalse(
			$spec_a->equals( $spec_b )
		);
	}

	/**
	 * Verify that equals() returns false when the price differs.
	 */
	public function test_equals_returns_false_for_different_price_text(): void {

		$spec_a = $this->create_spec();

		$spec_b = $this->create_spec(
			price_text: '$25.00',
		);

		$this->assertFalse(
			$spec_a->equals( $spec_b )
		);
	}

	/**
	 * Verify that equals() returns false when unknown tokens differ.
	 */
	public function test_equals_returns_false_for_different_unknown_tokens(): void {

		$spec_a = $this->create_spec();

		$spec_b = $this->create_spec(
			unknown_tokens: array(
				'Thin Thread',
			),
		);

		$this->assertFalse(
			$spec_a->equals( $spec_b )
		);
	}

	/**
	 * Verify that is_valid() returns true for a complete specification.
	 */
	public function test_is_valid_returns_true_for_complete_spec(): void {

		$spec = $this->create_spec();

		$this->assertTrue(
			$spec->is_valid()
		);
	}

	/**
	 * Verify that is_valid() returns false when the mesh count is missing.
	 */
	public function test_is_valid_returns_false_for_missing_mesh_count(): void {

		$spec = $this->create_spec(
			mesh_count: null,
		);

		$this->assertFalse(
			$spec->is_valid()
		);
	}

	/**
	 * Verify that is_valid() returns false when the thread diameter is missing.
	 */
	public function test_is_valid_returns_false_for_missing_thread_diameter(): void {

		$spec = $this->create_spec(
			thread_diameter: null,
		);

		$this->assertFalse(
			$spec->is_valid()
		);
	}

	/**
	 * Create specification fixture.
	 *
	 * @param int|null    $mesh_count Mesh count.
	 * @param int|null    $thread_diameter Thread diameter.
	 * @param string|null $modifier Modifier.
	 * @param string|null $color Color.
	 * @param string|null $pack_size Pack size.
	 * @param string|null $price_text Price token.
	 * @param bool        $recognized Whether recognized.
	 * @param string[]    $unknown_tokens Unknown tokens.
	 * @return Mesh_Specification
	 */
	private function create_spec(
		?int $mesh_count = 110,
		?int $thread_diameter = 80,
		?string $modifier = null,
		?string $color = 'Yellow',
		?string $pack_size = '10 Pack',
		?string $price_text = '$20.00',
		bool $recognized = true,
		array $unknown_tokens = array(),
	): Mesh_Specification {

		return new Mesh_Specification(
			'110/80 Yellow $20.00',
			$mesh_count,
			$thread_diameter,
			$modifier,
			$color,
			$pack_size,
			$price_text,
			$recognized,
			$unknown_tokens,
		);
	}
}
