<?php
/**
 * Tests for Customer Journey visitor UUIDs.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;

/**
 * Tests UUID generation and strict cookie-value validation.
 */
final class JourneyVisitorUuidTest extends TestCase {
	/**
	 * Generated values have the canonical version and variant bits.
	 *
	 * @return void
	 */
	public function test_generate_returns_canonical_version_four_uuid(): void {
		$uuid = Journey_Visitor_UUID::generate();

		self::assertSame( 36, strlen( $uuid ) );
		self::assertSame( '4', $uuid[14] );
		self::assertContains( $uuid[19], array( '8', '9', 'a', 'b' ) );
		self::assertTrue( Journey_Visitor_UUID::is_valid( value: $uuid ) );
	}

	/**
	 * Distinct random calls should not reuse a visitor identifier.
	 *
	 * @return void
	 */
	public function test_generate_does_not_reuse_values(): void {
		$uuids = array();

		for ( $index = 0; $index < 32; ++$index ) {
			$uuids[] = Journey_Visitor_UUID::generate();
		}

		self::assertCount( 32, array_unique( $uuids ) );
	}

	/**
	 * A canonical visitor identifier remains valid when read from a cookie.
	 *
	 * @return void
	 */
	public function test_valid_canonical_cookie_value_is_accepted(): void {
		self::assertTrue(
			Journey_Visitor_UUID::is_valid(
				value: '123e4567-e89b-42d3-a456-426614174000'
			)
		);
	}

	/**
	 * Reject malformed, non-v4, and noncanonical untrusted cookie values.
	 *
	 * @return void
	 */
	public function test_invalid_cookie_values_are_rejected(): void {
		$invalid_values = array(
			null,
			false,
			42,
			array( 'unexpected' ),
			'',
			'123e4567-e89b-42d3-a456-426614174000 ',
			"123e4567-e89b-42d3-a456-426614174000\n",
			'123E4567-E89B-42D3-A456-426614174000',
			'123e4567e89b42d3a456426614174000',
			'123e4567-e89b-12d3-a456-426614174000',
			'123e4567-e89b-42d3-7456-426614174000',
			'123e4567-e89b-42d3-a456-42661417400z',
			'0123e4567-e89b-42d3-a456-426614174000',
		);

		foreach ( $invalid_values as $value ) {
			self::assertFalse(
				Journey_Visitor_UUID::is_valid( value: $value )
			);
		}
	}
}
