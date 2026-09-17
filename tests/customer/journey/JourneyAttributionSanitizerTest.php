<?php
/**
 * Tests for Customer Journey traffic attribution sanitization.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;

/**
 * Tests strict path, referrer, and UTM field extraction.
 */
final class JourneyAttributionSanitizerTest extends TestCase {
	/**
	 * Capture only approved fields from a request with sensitive query data.
	 *
	 * @return void
	 */
	public function test_extracts_path_host_and_whitelisted_utm_values(): void {
		$attribution = ( new Journey_Attribution_Sanitizer() )->sanitize(
			request_uri: '/products/widget?email=buyer%40example.com&password=secret&utm_source=News%20Letter&utm_medium=email&utm_campaign=autumn&utm_term=blue+widget&utm_content=%3Cb%3Ebutton%3C%2Fb%3E#private',
			referrer_url: 'https://WWW.Example.ORG.:8443/account?token=secret#private',
		);

		self::assertSame(
			array(
				'landing_path'  => '/products/widget',
				'referrer_host' => 'www.example.org',
				'utm_source'    => 'News Letter',
				'utm_medium'    => 'email',
				'utm_campaign'  => 'autumn',
				'utm_term'      => 'blue widget',
				'utm_content'   => 'button',
			),
			$attribution
		);
	}

	/**
	 * An empty query contributes no attribution values.
	 *
	 * @return void
	 */
	public function test_root_path_and_missing_campaign_values(): void {
		self::assertSame(
			array(
				'landing_path'  => '/',
				'referrer_host' => null,
				'utm_source'    => null,
				'utm_medium'    => null,
				'utm_campaign'  => null,
				'utm_term'      => null,
				'utm_content'   => null,
			),
			( new Journey_Attribution_Sanitizer() )->sanitize(
				request_uri: '/?nonce=secret#section',
				referrer_url: null,
			)
		);
	}

	/**
	 * An absolute or protocol-relative request target cannot supply landing data.
	 *
	 * @return void
	 */
	public function test_rejects_non_relative_request_targets(): void {
		$sanitizer = new Journey_Attribution_Sanitizer();

		foreach (
			array(
				'https://other.example/path?utm_source=ad',
				'//other.example/path?utm_source=ad',
				'path?utm_source=ad',
				'',
			) as $request_uri
		) {
			$attribution = $sanitizer->sanitize( request_uri: $request_uri, referrer_url: null );
			self::assertNull( $attribution['landing_path'] );
			self::assertNull( $attribution['utm_source'] );
		}
	}

	/**
	 * A bad path cannot reach the bounded landing column.
	 *
	 * @return void
	 */
	public function test_rejects_unsafe_or_oversized_landing_paths(): void {
		$sanitizer = new Journey_Attribution_Sanitizer();

		foreach (
			array(
				'/products/secret%0Avalue',
				'/products/with space',
				'/products\\private',
				'/' . str_repeat( 'a', 1024 ),
				"/products/\nprivate",
			) as $request_uri
		) {
			self::assertNull(
				$sanitizer->sanitize( request_uri: $request_uri, referrer_url: null )['landing_path']
			);
		}
	}

	/**
	 * Referrers retain only validated HTTP hostnames.
	 *
	 * @return void
	 */
	public function test_rejects_referrer_urls_without_a_safe_hostname(): void {
		$sanitizer = new Journey_Attribution_Sanitizer();

		foreach (
			array(
				'javascript:alert(1)',
				'ftp://example.org/file',
				'https://user:password@example.org/path',
				'https://127.0.0.1/path',
				'https://bad..example.org/path',
				'https://example.org/' . str_repeat( 'a', 8192 ),
				"https://example.org/\nprivate",
			) as $referrer_url
		) {
			self::assertNull(
				$sanitizer->sanitize( request_uri: '/landing', referrer_url: $referrer_url )['referrer_host']
			);
		}
	}

	/**
	 * Campaign values must be scalar, clean, and within the schema limit.
	 *
	 * @return void
	 */
	public function test_rejects_malformed_or_oversized_utm_values(): void {
		$sanitizer   = new Journey_Attribution_Sanitizer();
		$attribution = $sanitizer->sanitize(
			request_uri: '/?utm_source[]=array&utm_medium=%0Ahidden&utm_campaign=' . str_repeat( 'a', 192 ) . '&utm_term=%FF&utm_content=%3Cb%3E%3C%2Fb%3E',
			referrer_url: null,
		);

		self::assertNull( $attribution['utm_source'] );
		self::assertNull( $attribution['utm_medium'] );
		self::assertNull( $attribution['utm_campaign'] );
		self::assertNull( $attribution['utm_term'] );
		self::assertNull( $attribution['utm_content'] );
		self::assertSame( '/', $attribution['landing_path'] );
	}

	/**
	 * The database column boundary accepts a value of exactly 191 bytes.
	 *
	 * @return void
	 */
	public function test_accepts_utm_value_at_column_limit(): void {
		$value = str_repeat( 'a', 191 );
		self::assertSame(
			$value,
			( new Journey_Attribution_Sanitizer() )->sanitize(
				request_uri: '/?utm_source=' . $value,
				referrer_url: null,
			)['utm_source']
		);
	}

	/**
	 * Obvious email addresses and URLs cannot become campaign labels.
	 *
	 * @return void
	 */
	public function test_rejects_email_and_url_utm_values(): void {
		$attribution = ( new Journey_Attribution_Sanitizer() )->sanitize(
			request_uri: '/?utm_source=buyer%40example.com&utm_medium=https%3A%2F%2Fexample.org%2F%3Ftoken%3Dsecret',
			referrer_url: null,
		);

		self::assertNull( $attribution['utm_source'] );
		self::assertNull( $attribution['utm_medium'] );
	}
}
