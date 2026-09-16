<?php
/**
 * Tests for the Customer Journey visitor cookie transport.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;

/**
 * Tests validated reads and secure, host-only visitor cookie writes.
 */
final class JourneyVisitorCookieTest extends TestCase {
	/**
	 * Original cookies to restore after each test.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_cookies = array();

	/**
	 * Reset cookie and WordPress filter state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->original_cookies = $_COOKIE;
		$_COOKIE                = array();

		$GLOBALS['shurloc_journey_cookie_test_calls']        = array();
		$GLOBALS['shurloc_journey_cookie_test_result']       = true;
		$GLOBALS['shurloc_journey_cookie_test_is_ssl']       = false;
		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;

		$GLOBALS['shurloc_test_filters'] = array();
	}

	/**
	 * Restore the current request after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_COOKIE                         = $this->original_cookies;
		$GLOBALS['shurloc_test_filters'] = array();

		parent::tearDown();
	}

	/**
	 * Only a canonical UUID from the request cookie is returned.
	 *
	 * @return void
	 */
	public function test_read_returns_valid_visitor_uuid(): void {
		$uuid                                    = '123e4567-e89b-42d3-a456-426614174000';
		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $uuid;

		self::assertSame( $uuid, ( new Journey_Visitor_Cookie() )->read() );
	}

	/**
	 * Missing and malformed cookies cannot become visitor identifiers.
	 *
	 * @return void
	 */
	public function test_read_rejects_missing_and_invalid_cookie_values(): void {
		$cookie = new Journey_Visitor_Cookie();

		self::assertNull( $cookie->read() );

		foreach ( array( '', 'not-a-uuid', '123E4567-E89B-42D3-A456-426614174000', array( 'x' ) ) as $value ) {
			$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $value;
			self::assertNull( $cookie->read() );
		}
	}

	/**
	 * The default cookie is persistent, host-only, HttpOnly, and SameSite Lax.
	 *
	 * @return void
	 */
	public function test_write_uses_secure_cookie_options_and_default_lifetime(): void {
		$uuid   = '123e4567-e89b-42d3-a456-426614174000';
		$before = time();

		self::assertTrue( ( new Journey_Visitor_Cookie() )->write( uuid: $uuid ) );

		$after = time();
		self::assertCount( 1, $GLOBALS['shurloc_journey_cookie_test_calls'] );

		$cookie = $GLOBALS['shurloc_journey_cookie_test_calls'][0];
		self::assertSame( Journey_Visitor_Cookie::NAME, $cookie['name'] );
		self::assertSame( $uuid, $cookie['value'] );
		self::assertSame( '/', $cookie['options']['path'] );
		self::assertFalse( $cookie['options']['secure'] );
		self::assertTrue( $cookie['options']['httponly'] );
		self::assertSame( 'Lax', $cookie['options']['samesite'] );
		self::assertArrayNotHasKey( 'domain', $cookie['options'] );
		self::assertGreaterThanOrEqual(
			$before + Journey_Visitor_Cookie::DEFAULT_LIFETIME_SECONDS,
			$cookie['options']['expires']
		);
		self::assertLessThanOrEqual(
			$after + Journey_Visitor_Cookie::DEFAULT_LIFETIME_SECONDS,
			$cookie['options']['expires']
		);
		self::assertArrayNotHasKey( Journey_Visitor_Cookie::NAME, $_COOKIE );
	}

	/**
	 * HTTPS requests set Secure without changing the browser UUID in PHP.
	 *
	 * @return void
	 */
	public function test_write_sets_secure_on_https(): void {
		$GLOBALS['shurloc_journey_cookie_test_is_ssl'] = true;
		$uuid                                    = '123e4567-e89b-42d3-a456-426614174000';
		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $uuid;

		self::assertTrue( ( new Journey_Visitor_Cookie() )->write( uuid: $uuid ) );
		self::assertTrue( $GLOBALS['shurloc_journey_cookie_test_calls'][0]['options']['secure'] );
		self::assertSame( $uuid, $_COOKIE[ Journey_Visitor_Cookie::NAME ] );
	}

	/**
	 * A site can configure the browser lifetime independently of data retention.
	 *
	 * @return void
	 */
	public function test_write_uses_filtered_lifetime(): void {
		add_filter(
			Journey_Visitor_Cookie::LIFETIME_FILTER,
			static fn (): int => 600
		);

		$before = time();
		self::assertTrue(
			( new Journey_Visitor_Cookie() )->write(
				uuid: '123e4567-e89b-42d3-a456-426614174000'
			)
		);
		$after = time();

		self::assertGreaterThanOrEqual( $before + 600, $GLOBALS['shurloc_journey_cookie_test_calls'][0]['options']['expires'] );
		self::assertLessThanOrEqual( $after + 600, $GLOBALS['shurloc_journey_cookie_test_calls'][0]['options']['expires'] );
	}

	/**
	 * Invalid identifiers and malformed lifetime settings cannot send a cookie.
	 *
	 * @return void
	 */
	public function test_write_rejects_invalid_uuid_and_lifetime(): void {
		$cookie = new Journey_Visitor_Cookie();
		self::assertFalse( $cookie->write( uuid: 'not-a-uuid' ) );

		foreach ( array( 0, -1, '600', true, PHP_INT_MAX ) as $lifetime ) {
			$GLOBALS['shurloc_test_filters'] = array();
			add_filter(
				Journey_Visitor_Cookie::LIFETIME_FILTER,
				static fn () => $lifetime
			);

			self::assertFalse(
				$cookie->write( uuid: '123e4567-e89b-42d3-a456-426614174000' )
			);
		}

		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}

	/**
	 * Cookie API errors and already-sent headers are reported to the caller.
	 *
	 * @return void
	 */
	public function test_write_reports_header_failures(): void {
		$cookie = new Journey_Visitor_Cookie();
		$uuid   = '123e4567-e89b-42d3-a456-426614174000';

		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = true;
		self::assertFalse( $cookie->write( uuid: $uuid ) );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );

		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;
		$GLOBALS['shurloc_journey_cookie_test_result']       = false;
		self::assertFalse( $cookie->write( uuid: $uuid ) );
		self::assertCount( 1, $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}

	/**
	 * Clearing expires the browser value and removes it from this request.
	 *
	 * @return void
	 */
	public function test_clear_expires_cookie_and_removes_request_value(): void {
		$GLOBALS['shurloc_journey_cookie_test_is_ssl'] = true;
		$_COOKIE[ Journey_Visitor_Cookie::NAME ]       = '123e4567-e89b-42d3-a456-426614174000';

		self::assertTrue( ( new Journey_Visitor_Cookie() )->clear() );

		$cookie = $GLOBALS['shurloc_journey_cookie_test_calls'][0];
		self::assertSame( Journey_Visitor_Cookie::NAME, $cookie['name'] );
		self::assertSame( '', $cookie['value'] );
		self::assertLessThan( time(), $cookie['options']['expires'] );
		self::assertSame( '/', $cookie['options']['path'] );
		self::assertTrue( $cookie['options']['secure'] );
		self::assertTrue( $cookie['options']['httponly'] );
		self::assertSame( 'Lax', $cookie['options']['samesite'] );
		self::assertArrayNotHasKey( Journey_Visitor_Cookie::NAME, $_COOKIE );
	}

	/**
	 * A failed expiry header must not hide the current request's cookie.
	 *
	 * @return void
	 */
	public function test_clear_preserves_request_cookie_when_header_fails(): void {
		$uuid                                    = '123e4567-e89b-42d3-a456-426614174000';
		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $uuid;
		$cookie                                  = new Journey_Visitor_Cookie();

		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = true;
		self::assertFalse( $cookie->clear() );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );

		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;
		$GLOBALS['shurloc_journey_cookie_test_result']       = false;
		self::assertFalse( $cookie->clear() );
		self::assertSame( $uuid, $_COOKIE[ Journey_Visitor_Cookie::NAME ] );
	}
}
