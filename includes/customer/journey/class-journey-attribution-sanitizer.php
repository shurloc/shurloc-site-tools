<?php
/**
 * Customer Journey traffic attribution sanitization.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

/**
 * Reduce request URLs and referrers to approved attribution fields.
 *
 * @phpstan-type Attribution array{
 *     landing_path:string|null,
 *     referrer_host:string|null,
 *     utm_source:string|null,
 *     utm_medium:string|null,
 *     utm_campaign:string|null,
 *     utm_term:string|null,
 *     utm_content:string|null
 * }
 */
final class Journey_Attribution_Sanitizer {
	/**
	 * Maximum request or referrer length accepted for parsing.
	 */
	private const MAX_INPUT_BYTES = 8192;

	/**
	 * Journey landing-path column length.
	 */
	private const MAX_PATH_BYTES = 1024;

	/**
	 * Journey referrer-host column length.
	 */
	private const MAX_HOST_BYTES = 255;

	/**
	 * Journey UTM column length.
	 */
	private const MAX_UTM_BYTES = 191;

	/**
	 * Whitelisted campaign query parameters.
	 *
	 * @var list<string>
	 */
	private const UTM_KEYS = array(
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
	);

	/**
	 * Extract only path, host, and whitelisted campaign fields.
	 *
	 * The request target must be a relative path, as supplied by the server's
	 * REQUEST_URI. Query strings and fragments are never retained. A malformed
	 * or oversized input contributes no attribution rather than being stored.
	 *
	 * @param string      $request_uri  Server request target.
	 * @param string|null $referrer_url HTTP referrer URL, when provided.
	 * @return Attribution Sanitized attribution fields.
	 */
	public function sanitize( string $request_uri, ?string $referrer_url ): array {
		$parts = $this->request_parts( uri: $request_uri );
		$query = array();

		if ( null !== $parts && isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		return array(
			'landing_path'  => null === $parts ? null : $this->landing_path( parts: $parts ),
			'referrer_host' => $this->referrer_host( url: $referrer_url ),
			'utm_source'    => $this->utm_value( query: $query, key: self::UTM_KEYS[0] ),
			'utm_medium'    => $this->utm_value( query: $query, key: self::UTM_KEYS[1] ),
			'utm_campaign'  => $this->utm_value( query: $query, key: self::UTM_KEYS[2] ),
			'utm_term'      => $this->utm_value( query: $query, key: self::UTM_KEYS[3] ),
			'utm_content'   => $this->utm_value( query: $query, key: self::UTM_KEYS[4] ),
		);
	}

	/**
	 * Parse a bounded relative request target.
	 *
	 * @param string $uri Request target.
	 * @return array<string,mixed>|null Parsed parts, or null when invalid.
	 */
	private function request_parts( string $uri ): ?array {
		if ( ! $this->safe_input( value: $uri ) ) {
			return null;
		}

		$parts = wp_parse_url( $uri );
		if (
			! is_array( $parts ) ||
			isset( $parts['scheme'] ) ||
			isset( $parts['host'] ) ||
			isset( $parts['user'] ) ||
			isset( $parts['pass'] ) ||
			isset( $parts['port'] )
		) {
			return null;
		}

		$path = $parts['path'] ?? '/';
		if ( '' === $path ||
			! str_starts_with( $path, '/' ) || str_starts_with( $path, '//' ) ) {
			return null;
		}

		return $parts;
	}

	/**
	 * Retain a safe path without query parameters or fragments.
	 *
	 * @param array<string,mixed> $parts Parsed request target.
	 * @return string|null Landing path, or null when invalid.
	 */
	private function landing_path( array $parts ): ?string {
		$path = $parts['path'] ?? '/';
		if ( ! is_string( $path ) || strlen( $path ) > self::MAX_PATH_BYTES ||
			str_contains( $path, '\\' ) || str_contains( $path, ' ' ) ||
			1 === preg_match( '/%(?:0[0-9a-f]|1[0-9a-f]|7f)/i', $path ) ) {
			return null;
		}

		return $path;
	}

	/**
	 * Retain only the hostname from an HTTP or HTTPS referrer.
	 *
	 * @param string|null $url Referrer URL.
	 * @return string|null Lowercase host, or null when invalid.
	 */
	private function referrer_host( ?string $url ): ?string {
		if ( null === $url || ! $this->safe_input( value: $url ) ) {
			return null;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ||
			isset( $parts['user'] ) || isset( $parts['pass'] ) ||
			! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return null;
		}

		$host = strtolower( rtrim( $parts['host'], '.' ) );
		if ( '' === $host || strlen( $host ) > self::MAX_HOST_BYTES ||
			false !== filter_var( $host, FILTER_VALIDATE_IP ) ||
			false === filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
			return null;
		}

		return $host;
	}

	/**
	 * Sanitize one whitelisted UTM value without retaining other query data.
	 *
	 * @param array<int|string,mixed> $query Parsed query values.
	 * @param string                  $key   Whitelisted key.
	 * @return string|null Sanitized value, or null when absent or invalid.
	 */
	private function utm_value( array $query, string $key ): ?string {
		$value = $query[ $key ] ?? null;
		if ( ! is_string( $value ) || '' === $value ||
			false === preg_match( '//u', $value ) ||
			1 === preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
			return null;
		}

		$clean = sanitize_text_field( $value );
		if ( '' === $clean || strlen( $clean ) > self::MAX_UTM_BYTES ||
			false !== filter_var( $clean, FILTER_VALIDATE_EMAIL ) ||
			str_contains( $clean, '://' ) ) {
			return null;
		}

		return $clean;
	}

	/**
	 * Reject oversized, invalid UTF-8, and control-character inputs.
	 *
	 * @param string $value Request URI or referrer URL.
	 * @return bool Whether it is safe to parse.
	 */
	private function safe_input( string $value ): bool {
		return '' !== $value && strlen( $value ) <= self::MAX_INPUT_BYTES &&
			1 === preg_match( '//u', $value ) &&
			0 === preg_match( '/[\x00-\x1F\x7F]/', $value );
	}
}
