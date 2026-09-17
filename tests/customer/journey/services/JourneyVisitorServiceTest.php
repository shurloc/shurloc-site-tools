<?php
/**
 * Tests for Customer Journey visitor identity resolution.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Services;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Policy;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Test_User;
use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_Cookie;
use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_UUID;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Tests consent gating, anonymous identity, login, and storage failures.
 */
final class JourneyVisitorServiceTest extends TestCase {
	/**
	 * Database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Original request cookies.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_cookies = array();

	/**
	 * Prepare a storefront request with ready Journey storage.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->original_cookies = $_COOKIE;
		$_COOKIE                = array();

		$GLOBALS['shurloc_test_options']                     = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$GLOBALS['shurloc_test_filters']                     = array();
		$GLOBALS['shurloc_test_is_admin']                    = false;
		$GLOBALS['shurloc_test_doing_ajax']                  = false;
		$GLOBALS['shurloc_journey_test_doing_cron']          = false;
		$GLOBALS['shurloc_journey_test_current_user']        = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_cookie_test_calls']        = array();
		$GLOBALS['shurloc_journey_cookie_test_result']       = true;
		$GLOBALS['shurloc_journey_cookie_test_is_ssl']       = false;
		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;

		$this->database = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = $this->database;
	}

	/**
	 * Restore request and database globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_COOKIE                                       = $this->original_cookies;
		$GLOBALS['shurloc_test_options']               = array();
		$GLOBALS['shurloc_test_filters']               = array();
		$GLOBALS['shurloc_test_is_admin']              = true;
		$GLOBALS['shurloc_test_doing_ajax']            = false;
		$GLOBALS['shurloc_journey_test_doing_cron']    = false;
		$GLOBALS['shurloc_journey_test_current_user']  = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_cookie_test_calls']  = array();
		$GLOBALS['shurloc_journey_cookie_test_result'] = true;
		$GLOBALS['shurloc_journey_cookie_test_is_ssl'] = false;
		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * A new anonymous browser receives only an opaque UUID cookie.
	 *
	 * @return void
	 */
	public function test_new_anonymous_visitor_gets_cookie_row_and_period(): void {
		$before   = gmdate( 'Y-m-d H:i:s' );
		$identity = ( new Journey_Visitor_Service() )->resolve();
		$after    = gmdate( 'Y-m-d H:i:s' );

		self::assertIsArray( $identity );
		self::assertSame( 1, $identity['visitor_id'] );
		self::assertSame( 1, $identity['identity_period_id'] );
		self::assertNull( $identity['user_id_at_event'] );
		self::assertTrue( Journey_Visitor_UUID::is_valid( value: $identity['visitor_uuid'] ) );
		self::assertSame( $identity['visitor_uuid'], $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'] );
		self::assertSame( Journey_Visitor_Cookie::NAME, $GLOBALS['shurloc_journey_cookie_test_calls'][0]['name'] );
		self::assertCount( 1, $this->database->visitor_rows );
		self::assertCount( 1, $this->database->periods );
		self::assertGreaterThanOrEqual( $before, $this->database->visitor_rows[ $identity['visitor_uuid'] ]['created_at'] );
		self::assertLessThanOrEqual( $after, $this->database->visitor_rows[ $identity['visitor_uuid'] ]['created_at'] );
		self::assertArrayNotHasKey( Journey_Visitor_Cookie::NAME, $_COOKIE );
	}

	/**
	 * A returning browser retains its visitor and anonymous period IDs.
	 *
	 * @return void
	 */
	public function test_returning_anonymous_visitor_reuses_cookie_and_period(): void {
		$service = new Journey_Visitor_Service();
		$first   = $service->resolve();
		self::assertIsArray( $first );

		$_COOKIE[ Journey_Visitor_Cookie::NAME ]      = $first['visitor_uuid'];
		$GLOBALS['shurloc_journey_cookie_test_calls'] = array();
		$returned                                     = $service->resolve();

		self::assertSame( $first, $returned );
		self::assertCount( 1, $this->database->visitor_rows );
		self::assertCount( 1, $this->database->periods );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}

	/**
	 * Login keeps the UUID while linking earlier anonymous activity.
	 *
	 * @return void
	 */
	public function test_login_preserves_uuid_and_links_prior_anonymous_period(): void {
		$service   = new Journey_Visitor_Service();
		$anonymous = $service->resolve();
		self::assertIsArray( $anonymous );

		$_COOKIE[ Journey_Visitor_Cookie::NAME ]      = $anonymous['visitor_uuid'];
		$GLOBALS['shurloc_journey_cookie_test_calls'] = array();
		$GLOBALS['shurloc_journey_test_current_user'] = new Journey_Collection_Test_User( 37 );

		$authenticated = $service->resolve();
		self::assertIsArray( $authenticated );
		self::assertSame( $anonymous['visitor_uuid'], $authenticated['visitor_uuid'] );
		self::assertSame( $anonymous['visitor_id'], $authenticated['visitor_id'] );
		self::assertSame( 2, $authenticated['identity_period_id'] );
		self::assertSame( 37, $authenticated['user_id_at_event'] );
		self::assertSame( 37, $this->database->periods[1]['user_id'] );
		self::assertNotNull( $this->database->periods[1]['linked_at'] );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}

	/**
	 * One account can be associated with more than one browser UUID.
	 *
	 * @return void
	 */
	public function test_same_user_can_resolve_multiple_browser_visitors(): void {
		$GLOBALS['shurloc_journey_test_current_user'] = new Journey_Collection_Test_User( 37 );
		$service                                      = new Journey_Visitor_Service();

		$first = $service->resolve();
		self::assertIsArray( $first );
		$second = $service->resolve();
		self::assertIsArray( $second );

		self::assertNotSame( $first['visitor_uuid'], $second['visitor_uuid'] );
		self::assertNotSame( $first['visitor_id'], $second['visitor_id'] );
		self::assertSame( 37, $this->database->periods[1]['user_id'] );
		self::assertSame( 37, $this->database->periods[2]['user_id'] );
	}

	/**
	 * The central consent rule blocks cookie and database work.
	 *
	 * @return void
	 */
	public function test_consent_denial_prevents_visitor_collection(): void {
		add_filter( Journey_Collection_Policy::COLLECTION_ALLOWED_FILTER, static fn (): bool => false );

		self::assertNull( ( new Journey_Visitor_Service() )->resolve() );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
		self::assertSame( array(), $this->database->visitor_rows );
		self::assertSame( array(), $this->database->queries );
	}

	/**
	 * A role filter excludes the current authenticated user from collection.
	 *
	 * @return void
	 */
	public function test_role_exclusion_prevents_visitor_collection(): void {
		$user        = new Journey_Collection_Test_User( 37 );
		$user->roles = array( 'shop_manager' );
		$GLOBALS['shurloc_journey_test_current_user'] = $user;
		add_filter(
			Journey_Collection_Policy::EXCLUDED_ROLES_FILTER,
			static fn (): array => array( 'shop_manager' )
		);

		self::assertNull( ( new Journey_Visitor_Service() )->resolve() );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
		self::assertSame( array(), $this->database->visitor_rows );
	}

	/**
	 * An unavailable schema cannot set a browser identifier or insert rows.
	 *
	 * @return void
	 */
	public function test_schema_gate_prevents_cookie_and_database_work(): void {
		$GLOBALS['shurloc_test_options'] = array();

		self::assertNull( ( new Journey_Visitor_Service() )->resolve() );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
		self::assertSame( array(), $this->database->visitor_rows );
	}

	/**
	 * Failed cookie writes cannot create browser identities in storage.
	 *
	 * @return void
	 */
	public function test_cookie_write_failure_prevents_database_work(): void {
		$GLOBALS['shurloc_journey_cookie_test_result'] = false;

		self::assertNull( ( new Journey_Visitor_Service() )->resolve() );
		self::assertCount( 1, $GLOBALS['shurloc_journey_cookie_test_calls'] );
		self::assertSame( array(), $this->database->visitor_rows );
		self::assertSame( array(), $this->database->periods );
	}

	/**
	 * A transient visitor insert error leaves a cookie that can retry later.
	 *
	 * @return void
	 */
	public function test_visitor_insert_failure_retries_with_same_uuid(): void {
		$this->database->fail_visitor_insert = true;
		$service                             = new Journey_Visitor_Service();

		self::assertNull( $service->resolve() );
		self::assertSame( array(), $this->database->visitor_rows );
		$uuid                                    = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];
		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $uuid;
		$this->database->fail_visitor_insert     = false;

		$identity = ( new Journey_Visitor_Service() )->resolve();
		self::assertIsArray( $identity );
		self::assertSame( $uuid, $identity['visitor_uuid'] );
		self::assertCount( 1, $this->database->visitor_rows );
	}

	/**
	 * A failed returning-visitor timestamp update blocks identity until retry.
	 *
	 * @return void
	 */
	public function test_returning_visitor_seen_update_failure_blocks_identity_and_retries(): void {
		$service = new Journey_Visitor_Service();
		$first   = $service->resolve();
		self::assertIsArray( $first );

		$uuid                                    = $first['visitor_uuid'];
		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $uuid;
		$old                                     = gmdate( 'Y-m-d H:i:s', time() - 3600 );
		$row                                     = $this->database->visitor_rows[ $uuid ];
		$row['last_seen_at']                     = $old;
		$this->database->visitor_rows[ $uuid ]   = $row;
		$this->database->visitors[ $uuid ]       = $row;
		$this->database->fail_update             = true;

		self::assertNull( $service->resolve() );
		self::assertSame( $old, $this->database->visitor_rows[ $uuid ]['last_seen_at'] );
		self::assertCount( 1, $this->database->periods );

		$this->database->fail_update = false;
		$before                      = gmdate( 'Y-m-d H:i:s' );
		$returned                    = $service->resolve();
		$after                       = gmdate( 'Y-m-d H:i:s' );

		self::assertSame( $first, $returned );
		self::assertGreaterThanOrEqual( $before, $this->database->visitor_rows[ $uuid ]['last_seen_at'] );
		self::assertLessThanOrEqual( $after, $this->database->visitor_rows[ $uuid ]['last_seen_at'] );
	}

	/**
	 * A failed period insert can recover on the next request with the same ID.
	 *
	 * @return void
	 */
	public function test_identity_period_failure_retries_with_same_visitor(): void {
		$this->database->fail_insert = true;
		$service                     = new Journey_Visitor_Service();

		self::assertNull( $service->resolve() );
		self::assertCount( 1, $this->database->visitor_rows );
		self::assertSame( array(), $this->database->periods );
		$uuid                                    = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];
		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $uuid;
		$this->database->fail_insert             = false;

		$identity = ( new Journey_Visitor_Service() )->resolve();
		self::assertIsArray( $identity );
		self::assertSame( 1, $identity['visitor_id'] );
		self::assertSame( $uuid, $identity['visitor_uuid'] );
		self::assertSame( 1, $identity['identity_period_id'] );
		self::assertCount( 1, $this->database->visitor_rows );
	}

	/**
	 * Invalid incoming cookie values are replaced with valid random UUIDs.
	 *
	 * @return void
	 */
	public function test_invalid_cookie_is_replaced(): void {
		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = 'invalid';

		$identity = ( new Journey_Visitor_Service() )->resolve();

		self::assertIsArray( $identity );
		self::assertTrue( Journey_Visitor_UUID::is_valid( value: $identity['visitor_uuid'] ) );
		self::assertNotSame( 'invalid', $identity['visitor_uuid'] );
		self::assertSame( $identity['visitor_uuid'], $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'] );
	}
}
