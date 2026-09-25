<?php
/**
 * Tests for the Customer Journey WooCommerce cart-session token.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Checkout\Test_WooCommerce;

/**
 * Tests token creation, reuse, validation, and hashing.
 */
final class JourneyCartSessionTokenTest extends TestCase {

	/**
	 * WooCommerce test instance.
	 *
	 * @var Test_WooCommerce
	 */
	private Test_WooCommerce $woocommerce;

	/**
	 * Prepare a WooCommerce session for each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->woocommerce                   = new Test_WooCommerce();
		$GLOBALS['shurloc_test_woocommerce'] = $this->woocommerce;
	}

	/**
	 * Restore the shared WooCommerce test global.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_woocommerce'] = null;

		parent::tearDown();
	}

	/**
	 * Verify a valid stored token is reused without exposing it to the caller.
	 *
	 * @return void
	 */
	public function test_valid_stored_token_is_reused_as_hash(): void {
		$token = str_repeat( 'ab', 32 );
		$this->woocommerce->session->set( Journey_Cart_Session_Token::SESSION_KEY, $token );

		$result = ( new Journey_Cart_Session_Token() )->get_or_create_hash();

		self::assertSame( hash( 'sha256', $token ), $result );
		self::assertNotSame( $token, $result );
		self::assertSame(
			$token,
			$this->woocommerce->session->get( Journey_Cart_Session_Token::SESSION_KEY )
		);
	}

	/**
	 * Verify an absent token is generated once and reused by later calls.
	 *
	 * @return void
	 */
	public function test_missing_token_is_created_and_reused(): void {
		$service    = new Journey_Cart_Session_Token();
		$first_hash = $service->get_or_create_hash();
		$token      = $this->woocommerce->session->get( Journey_Cart_Session_Token::SESSION_KEY );

		self::assertIsString( $token );
		self::assertMatchesRegularExpression( '/\A[0-9a-f]{64}\z/', $token );
		self::assertSame( hash( 'sha256', $token ), $first_hash );
		self::assertSame( $first_hash, $service->get_or_create_hash() );
		self::assertSame(
			$token,
			$this->woocommerce->session->get( Journey_Cart_Session_Token::SESSION_KEY )
		);
	}

	/**
	 * Verify malformed stored values are replaced instead of normalized.
	 *
	 * @return void
	 */
	public function test_invalid_stored_token_is_replaced(): void {
		$this->woocommerce->session->set(
			Journey_Cart_Session_Token::SESSION_KEY,
			str_repeat( 'A', 64 )
		);

		$result = ( new Journey_Cart_Session_Token() )->get_or_create_hash();
		$token  = $this->woocommerce->session->get( Journey_Cart_Session_Token::SESSION_KEY );

		self::assertIsString( $token );
		self::assertMatchesRegularExpression( '/\A[0-9a-f]{64}\z/', $token );
		self::assertNotSame( str_repeat( 'A', 64 ), $token );
		self::assertSame( hash( 'sha256', $token ), $result );
	}

	/**
	 * Verify token hashing rejects untrusted malformed values.
	 *
	 * @return void
	 */
	public function test_hash_token_rejects_invalid_values(): void {
		$invalid_values = array(
			null,
			false,
			42,
			array( 'unexpected' ),
			'',
			str_repeat( 'a', 63 ),
			str_repeat( 'a', 65 ),
			str_repeat( 'A', 64 ),
			str_repeat( 'g', 64 ),
			str_repeat( 'a', 64 ) . "\n",
		);

		foreach ( $invalid_values as $value ) {
			self::assertNull( Journey_Cart_Session_Token::hash_token( token: $value ) );
		}
	}

	/**
	 * Verify no token is created before WooCommerce initializes its session.
	 *
	 * @return void
	 */
	public function test_unavailable_woocommerce_session_returns_null(): void {
		$GLOBALS['shurloc_test_woocommerce'] = new \WooCommerce();

		self::assertNull( ( new Journey_Cart_Session_Token() )->get_or_create_hash() );
	}
}
