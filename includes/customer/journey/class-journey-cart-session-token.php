<?php
/**
 * Customer Journey WooCommerce cart-session correlation token.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

/**
 * Stores an opaque token in the WooCommerce session and exposes only its hash.
 */
final class Journey_Cart_Session_Token {

	/**
	 * WooCommerce session-data key for the raw correlation token.
	 */
	public const SESSION_KEY = 'shurloc_journey_cart_token';

	/**
	 * Read or create the current cart-session token and return only its hash.
	 *
	 * A random-source failure propagates instead of creating a predictable token.
	 * Invalid stored values are replaced with a new server-generated token.
	 *
	 * @return string|null Lowercase SHA-256 hash, or null without a WooCommerce session.
	 */
	public function get_or_create_hash(): ?string {
		$session = $this->get_session();

		if ( null === $session ||
			! method_exists( $session, 'get' ) ||
			! method_exists( $session, 'set' ) ) {
			return null;
		}

		$token_hash = self::hash_token(
			token: $session->get( self::SESSION_KEY )
		);

		if ( null !== $token_hash ) {
			return $token_hash;
		}

		$token = bin2hex( random_bytes( 32 ) );
		$session->set( self::SESSION_KEY, $token );

		return hash( 'sha256', $token );
	}

	/**
	 * Validate and hash an untrusted token value from WooCommerce session data.
	 *
	 * @param mixed $token Untrusted raw token.
	 * @return string|null Lowercase SHA-256 hash, or null for an invalid token.
	 */
	public static function hash_token( mixed $token ): ?string {
		if ( ! is_string( $token ) ||
			1 !== preg_match( '/\A[0-9a-f]{64}\z/', $token ) ) {
			return null;
		}

		return hash( 'sha256', $token );
	}

	/**
	 * Read WooCommerce's runtime-initialized public session dependency.
	 *
	 * @return object|null WooCommerce session object when initialized.
	 */
	private function get_session(): ?object {
		$properties = get_object_vars( WC() );
		$session    = $properties['session'] ?? null;

		return is_object( $session ) ? $session : null;
	}
}
