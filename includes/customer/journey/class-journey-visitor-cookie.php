<?php
/**
 * Customer Journey visitor cookie transport.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the opaque visitor identifier in a first-party cookie.
 *
 * Collection eligibility is decided by Journey_Collection_Policy before a
 * caller uses this transport to create or continue a visitor.
 */
final class Journey_Visitor_Cookie {
	/**
	 * Browser cookie containing only the visitor UUID.
	 */
	public const NAME = 'shurloc_visitor_id';

	/**
	 * Default browser cookie lifetime in seconds, separate from data retention.
	 */
	public const DEFAULT_LIFETIME_SECONDS = 365 * 24 * 60 * 60;

	/**
	 * Filter the positive integer browser cookie lifetime in seconds.
	 */
	public const LIFETIME_FILTER = 'shurloc_site_tools_journey_visitor_cookie_lifetime';

	/**
	 * Read only a valid, canonical visitor UUID from the current request.
	 *
	 * @return string|null Visitor UUID, or null when the cookie is absent or invalid.
	 */
	public function read(): ?string {
		$value = $_COOKIE[ self::NAME ] ?? null;

		if ( ! is_string( $value ) || ! Journey_Visitor_UUID::is_valid( value: $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Send a persistent, server-controlled visitor cookie.
	 *
	 * A malformed lifetime filter fails closed. The current request's cookie
	 * array is not changed; the browser sends the new value on its next request.
	 *
	 * @param string $uuid Canonical visitor UUID.
	 * @return bool Whether the cookie header was accepted for sending.
	 */
	public function write( string $uuid ): bool {
		if ( ! Journey_Visitor_UUID::is_valid( value: $uuid ) || headers_sent() ) {
			return false;
		}

		$lifetime = apply_filters( self::LIFETIME_FILTER, self::DEFAULT_LIFETIME_SECONDS );
		$now      = time();

		if ( ! is_int( $lifetime ) || 0 >= $lifetime || $lifetime > PHP_INT_MAX - $now ) {
			return false;
		}

		return setcookie(
			self::NAME,
			$uuid,
			$this->options( expires: $now + $lifetime )
		);
	}

	/**
	 * Expire the browser cookie using the same path and security options.
	 *
	 * @return bool Whether the expiry header was accepted for sending.
	 */
	public function clear(): bool {
		if ( headers_sent() ) {
			return false;
		}

		$sent = setcookie(
			self::NAME,
			'',
			$this->options( expires: time() - 3600 )
		);

		if ( $sent ) {
			unset( $_COOKIE[ self::NAME ] );
		}

		return $sent;
	}

	/**
	 * Build the shared host-only cookie options.
	 *
	 * @param int $expires Unix expiry time.
	 * @return array{expires:int,path:string,secure:bool,httponly:bool,samesite:string} Cookie options.
	 */
	private function options( int $expires ): array {
		$path = defined( 'COOKIEPATH' ) && is_string( COOKIEPATH ) && '' !== COOKIEPATH
			? COOKIEPATH
			: '/';

		return array(
			'expires'  => $expires,
			'path'     => $path,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
	}
}
