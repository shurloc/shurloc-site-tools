<?php
/**
 * Customer Journey visitor UUID handling.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

/**
 * Generates and validates opaque, canonical visitor UUIDs.
 */
final class Journey_Visitor_UUID {
	/**
	 * Generate a cryptographically random UUID version 4.
	 *
	 * A random-source failure must propagate instead of creating a predictable
	 * visitor identifier.
	 *
	 * @return string Lowercase UUID version 4.
	 */
	public static function generate(): string {
		$bytes = random_bytes( 16 );

		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );

		$hex = bin2hex( $bytes );

		return substr( $hex, 0, 8 ) . '-'
			. substr( $hex, 8, 4 ) . '-'
			. substr( $hex, 12, 4 ) . '-'
			. substr( $hex, 16, 4 ) . '-'
			. substr( $hex, 20, 12 );
	}

	/**
	 * Validate an untrusted cookie value without coercion or normalization.
	 *
	 * @param mixed $value Untrusted visitor UUID value.
	 * @return bool True only for a canonical lowercase UUID version 4.
	 */
	public static function is_valid( mixed $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		return 1 === preg_match(
			'/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
			$value
		);
	}
}
