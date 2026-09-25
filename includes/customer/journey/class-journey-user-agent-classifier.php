<?php
/**
 * Customer Journey user-agent classification.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

/**
 * Preserve a bounded user-agent value and derive normalized client metadata.
 *
 * @phpstan-type Classification array{
 *     client_type:string,
 *     client_name:string|null,
 *     device_type:string,
 *     classification_version:int
 * }
 * @phpstan-type ClassificationResult array{
 *     user_agent:string|null,
 *     client_type:string,
 *     client_name:string|null,
 *     device_type:string,
 *     classification_version:int
 * }
 */
final class Journey_User_Agent_Classifier {
	/**
	 * Current classifier rules version.
	 */
	public const CLASSIFICATION_VERSION = 1;

	/**
	 * Filter for normalized classification fields.
	 */
	public const CLASSIFICATION_FILTER =
		'shurloc_site_tools_journey_user_agent_classification';

	/**
	 * Maximum stored user-agent length in bytes.
	 */
	private const MAX_USER_AGENT_BYTES = 1024;

	/**
	 * Maximum normalized client-name length in bytes.
	 */
	private const MAX_CLIENT_NAME_BYTES = 64;

	/**
	 * Classify a raw HTTP user-agent header.
	 *
	 * Filters receive the normalized classification and the bounded user-agent
	 * value as a second argument. The bounded inspection value is not
	 * filterable. Malformed filter results fall back to the built-in result.
	 *
	 * @param string|null $user_agent Raw user-agent header value.
	 * @return ClassificationResult Bounded header and normalized metadata.
	 */
	public function classify( ?string $user_agent ): array {
		$stored_user_agent = $this->normalize_user_agent( user_agent: $user_agent );
		$classification    = $this->classify_client( user_agent: $stored_user_agent );
		$filtered          = apply_filters(
			self::CLASSIFICATION_FILTER,
			$classification,
			$stored_user_agent
		);
		$validated         = $this->validate_classification( classification: $filtered );

		if ( null === $validated ) {
			$validated = $classification;
		}

		return array(
			'user_agent'             => $stored_user_agent,
			'client_type'            => $validated['client_type'],
			'client_name'            => $validated['client_name'],
			'device_type'            => $validated['device_type'],
			'classification_version' => $validated['classification_version'],
		);
	}

	/**
	 * Prepare a header for safe storage without interpreting its tokens.
	 *
	 * @param string|null $user_agent Raw user-agent header value.
	 * @return string|null Bounded printable value, or null when empty.
	 */
	private function normalize_user_agent( ?string $user_agent ): ?string {
		if ( null === $user_agent ) {
			return null;
		}

		$user_agent = wp_check_invalid_utf8( $user_agent, true );
		$user_agent = preg_replace( '/[\x00-\x1F\x7F]/', '', $user_agent ) ?? '';
		$user_agent = trim( $user_agent );

		if ( '' === $user_agent ) {
			return null;
		}

		$user_agent = substr( $user_agent, 0, self::MAX_USER_AGENT_BYTES );

		return wp_check_invalid_utf8( $user_agent, true );
	}

	/**
	 * Derive the built-in classification for one normalized header.
	 *
	 * @param string|null $user_agent Bounded user-agent value.
	 * @return Classification Normalized classification.
	 */
	private function classify_client( ?string $user_agent ): array {
		if ( null === $user_agent ) {
			return $this->unknown_classification();
		}

		$client = $this->match_automated_client( user_agent: $user_agent );
		if ( null !== $client ) {
			return array(
				'client_type'            => $client['client_type'],
				'client_name'            => $client['client_name'],
				'device_type'            => 'unknown',
				'classification_version' => self::CLASSIFICATION_VERSION,
			);
		}

		$client_name = $this->match_browser( user_agent: $user_agent );
		if ( null === $client_name ) {
			return $this->unknown_classification();
		}

		return array(
			'client_type'            => 'browser',
			'client_name'            => $client_name,
			'device_type'            => $this->classify_browser_device( user_agent: $user_agent ),
			'classification_version' => self::CLASSIFICATION_VERSION,
		);
	}

	/**
	 * Match crawlers and HTTP clients before browser compatibility tokens.
	 *
	 * @param string $user_agent Bounded user-agent value.
	 * @return array{client_type:string,client_name:string}|null Matched client.
	 */
	private function match_automated_client( string $user_agent ): ?array {
		$crawlers = array(
			'/Google-InspectionTool/i' => 'Google Inspection Tool',
			'/Googlebot/i'             => 'Googlebot',
			'/bingbot/i'               => 'Bingbot',
			'/DuckDuckBot/i'           => 'DuckDuckBot',
			'/Baiduspider/i'           => 'Baiduspider',
			'/YandexBot/i'             => 'YandexBot',
			'/Slurp/i'                 => 'Yahoo Slurp',
			'/facebookexternalhit/i'   => 'Facebook crawler',
			'/HeadlessChrome/i'        => 'Headless Chrome',
		);

		foreach ( $crawlers as $pattern => $client_name ) {
			if ( 1 === preg_match( $pattern, $user_agent ) ) {
				return array(
					'client_type' => 'crawler',
					'client_name' => $client_name,
				);
			}
		}

		if ( 1 === preg_match( '/(?:^|[\s(;])[a-z0-9_-]*(?:bot|crawler|spider)(?:[-_][a-z0-9]+)*(?=\/|[\s;)]|$)/i', $user_agent ) ) {
			return array(
				'client_type' => 'crawler',
				'client_name' => 'Crawler',
			);
		}

		$http_clients = array(
			'/(?:^|[\s(])curl(?:\/|[\s)]|$)/i'            => 'curl',
			'/(?:^|[\s(])Wget(?:\/|[\s)]|$)/i'            => 'Wget',
			'/(?:^|[\s(])python-requests(?:\/|[\s)]|$)/i' => 'Python Requests',
			'/(?:^|[\s(])Go-http-client(?:\/|[\s)]|$)/i'  => 'Go HTTP Client',
			'/(?:^|[\s(])okhttp(?:\/|[\s)]|$)/i'          => 'OkHttp',
			'/(?:^|[\s(])libwww-perl(?:\/|[\s)]|$)/i'     => 'libwww-perl',
		);

		foreach ( $http_clients as $pattern => $client_name ) {
			if ( 1 === preg_match( $pattern, $user_agent ) ) {
				return array(
					'client_type' => 'http_client',
					'client_name' => $client_name,
				);
			}
		}

		return null;
	}

	/**
	 * Match common browsers in precedence order.
	 *
	 * @param string $user_agent Bounded user-agent value.
	 * @return string|null Normalized browser name.
	 */
	private function match_browser( string $user_agent ): ?string {
		$browsers = array(
			'/\bEdg(?:A|iOS)?\//i'             => 'Edge',
			'/\bOPR\//i'                       => 'Opera',
			'/SamsungBrowser\//i'              => 'Samsung Internet',
			'/\b(?:CriOS|Chrome)\//i'          => 'Chrome',
			'/\b(?:FxiOS|Firefox)\//i'         => 'Firefox',
			'/\b(?:MSIE\s|Trident\/)/i'        => 'Internet Explorer',
			'/\bVersion\/[^\s]+.*\bSafari\//i' => 'Safari',
		);

		foreach ( $browsers as $pattern => $client_name ) {
			if ( 1 === preg_match( $pattern, $user_agent ) ) {
				return $client_name;
			}
		}

		return null;
	}

	/**
	 * Classify the device family for a recognized browser.
	 *
	 * @param string $user_agent Bounded user-agent value.
	 * @return string Normalized device type.
	 */
	private function classify_browser_device( string $user_agent ): string {
		if (
			1 === preg_match( '/\b(?:iPad|Tablet)\b/i', $user_agent ) ||
			1 === preg_match( '/\bAndroid\b(?!.*\bMobile\b)/i', $user_agent ) ||
			1 === preg_match( '/\bMacintosh\b.*\bMobile\//i', $user_agent )
		) {
			return 'tablet';
		}

		if ( 1 === preg_match( '/\b(?:Mobile|iPhone|iPod|Windows Phone|Android)\b/i', $user_agent ) ) {
			return 'mobile';
		}

		if ( 1 === preg_match( '/\b(?:Windows NT|Macintosh|X11|Linux|CrOS)\b/i', $user_agent ) ) {
			return 'desktop';
		}

		return 'unknown';
	}

	/**
	 * Return the unclassified result for an absent or unknown header.
	 *
	 * @return Classification Unknown classification.
	 */
	private function unknown_classification(): array {
		return array(
			'client_type'            => 'unknown',
			'client_name'            => null,
			'device_type'            => 'unknown',
			'classification_version' => self::CLASSIFICATION_VERSION,
		);
	}

	/**
	 * Validate filtered metadata before it can reach session storage.
	 *
	 * @param mixed $classification Filtered classification.
	 * @return Classification|null Valid classification, or null on any error.
	 */
	private function validate_classification( $classification ): ?array {
		if ( ! is_array( $classification ) ) {
			return null;
		}

		$client_type            = $classification['client_type'] ?? null;
		$client_name            = $classification['client_name'] ?? null;
		$device_type            = $classification['device_type'] ?? null;
		$classification_version = $classification['classification_version'] ?? null;

		if (
			! is_string( $client_type ) ||
			1 !== preg_match( '/\A[a-z][a-z0-9_]{0,31}\z/', $client_type ) ||
			! is_string( $device_type ) ||
			1 !== preg_match( '/\A[a-z][a-z0-9_]{0,31}\z/', $device_type ) ||
			! is_int( $classification_version ) ||
			1 > $classification_version ||
			65535 < $classification_version
		) {
			return null;
		}

		if ( null !== $client_name ) {
			if (
				! is_string( $client_name ) ||
				'' === $client_name ||
				self::MAX_CLIENT_NAME_BYTES < strlen( $client_name ) ||
				1 === preg_match( '/[\x00-\x1F\x7F]/', $client_name )
			) {
				return null;
			}
		}

		return array(
			'client_type'            => $client_type,
			'client_name'            => $client_name,
			'device_type'            => $device_type,
			'classification_version' => $classification_version,
		);
	}
}
