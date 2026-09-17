<?php
/**
 * Tests for Customer Journey session resolution.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Services;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Policy;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Test_User;
use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_Cookie;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Tests accepted activity, policy gating, attribution, and timeout settings.
 */
final class JourneySessionServiceTest extends TestCase {
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
	 * Original server request context.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_server = array();

	/**
	 * Prepare an eligible storefront request with ready Journey storage.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->original_cookies = $_COOKIE;
		$this->original_server  = $_SERVER;
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
	 * Restore shared request and database globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_COOKIE                                       = $this->original_cookies;
		$_SERVER                                       = $this->original_server;
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
	 * Use the supplied page context, never the ingestion endpoint URI.
	 *
	 * @return void
	 */
	public function test_first_activity_creates_session_with_sanitized_page_context(): void {
		$_SERVER['REQUEST_URI'] = '/wp-json/shurloc/v1/journey';
		$this->database->prefix = 'shop_';

		$before  = gmdate( 'Y-m-d H:i:s' );
		$context = ( new Journey_Session_Service() )->resolve_for_activity(
			page_uri: '/product/widget?utm_source=newsletter&utm_medium=email&email=private%40example.org',
			referrer_url: 'https://Example.org/article?token=secret',
		);
		$after   = gmdate( 'Y-m-d H:i:s' );

		self::assertIsArray( $context );
		self::assertSame( 1, $context['visitor_id'] );
		self::assertSame( 1, $context['identity_period_id'] );
		self::assertNull( $context['user_id_at_event'] );
		self::assertSame( 1, $context['session_id'] );
		self::assertSame( $context['visitor_uuid'], $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'] );
		self::assertSame( 'shop_shurloc_journey_sessions', $this->database->insert_calls[2]['table'] );
		self::assertSame( '/product/widget', $this->database->sessions[1]['landing_path'] );
		self::assertSame( 'example.org', $this->database->sessions[1]['referrer_host'] );
		self::assertSame( 'newsletter', $this->database->sessions[1]['utm_source'] );
		self::assertSame( 'email', $this->database->sessions[1]['utm_medium'] );
		self::assertGreaterThanOrEqual( $before, $this->database->sessions[1]['started_at'] );
		self::assertLessThanOrEqual( $after, $this->database->sessions[1]['started_at'] );
		self::assertNotContains( 'private@example.org', array_values( $this->database->sessions[1] ) );
		self::assertNotContains( 'https://Example.org/article?token=secret', array_values( $this->database->sessions[1] ) );
	}

	/**
	 * A login in the same session changes event identity but not start snapshot.
	 *
	 * @return void
	 */
	public function test_returning_activity_reuses_session_and_preserves_start_attribution(): void {
		$service = new Journey_Session_Service();
		$first   = $service->resolve_for_activity( '/first?utm_source=initial' );
		self::assertIsArray( $first );

		$_COOKIE[ Journey_Visitor_Cookie::NAME ]      = $first['visitor_uuid'];
		$GLOBALS['shurloc_journey_test_current_user'] = new Journey_Collection_Test_User( 37 );
		$second                                       = $service->resolve_for_activity( '/later?utm_source=later' );

		self::assertIsArray( $second );
		self::assertSame( $first['visitor_id'], $second['visitor_id'] );
		self::assertSame( $first['session_id'], $second['session_id'] );
		self::assertSame( 37, $second['user_id_at_event'] );
		self::assertNotSame( $first['identity_period_id'], $second['identity_period_id'] );
		self::assertCount( 1, $this->database->sessions );
		self::assertSame( $first['identity_period_id'], $this->database->sessions[1]['identity_period_id'] );
		self::assertNull( $this->database->sessions[1]['user_id_at_start'] );
		self::assertSame( '/first', $this->database->sessions[1]['landing_path'] );
		self::assertSame( 'initial', $this->database->sessions[1]['utm_source'] );
	}

	/**
	 * The filtered timeout starts a new session at the configured gap.
	 *
	 * @return void
	 */
	public function test_positive_integer_timeout_filter_controls_new_session(): void {
		$service = new Journey_Session_Service();
		$first   = $service->resolve_for_activity( '/first' );
		self::assertIsArray( $first );

		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $first['visitor_uuid'];
		$past                                    = gmdate( 'Y-m-d H:i:s', time() - 61 );
		$row                                     = $this->database->sessions[1];
		$row['started_at']                       = $past;
		$row['last_activity_at']                 = $past;
		$this->database->sessions[1]             = $row;
		add_filter( Journey_Session_Service::TIMEOUT_FILTER, static fn (): int => 60 );

		$second = $service->resolve_for_activity( '/return' );

		self::assertIsArray( $second );
		self::assertSame( 2, $second['session_id'] );
		self::assertSame( $past, $this->database->sessions[1]['ended_at'] );
		self::assertSame( '/return', $this->database->sessions[2]['landing_path'] );
	}

	/**
	 * Invalid filter values retain the central default timeout.
	 *
	 * @return void
	 */
	public function test_invalid_timeout_filter_uses_default(): void {
		$service = new Journey_Session_Service();
		$first   = $service->resolve_for_activity();
		self::assertIsArray( $first );

		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $first['visitor_uuid'];
		$past                                    = gmdate( 'Y-m-d H:i:s', time() - 61 );
		$row                                     = $this->database->sessions[1];
		$row['started_at']                       = $past;
		$row['last_activity_at']                 = $past;
		$this->database->sessions[1]             = $row;
		add_filter( Journey_Session_Service::TIMEOUT_FILTER, static fn (): string => '60' );

		$second = $service->resolve_for_activity();

		self::assertIsArray( $second );
		self::assertSame( 1, $second['session_id'] );
		self::assertCount( 1, $this->database->sessions );
		self::assertNull( $this->database->sessions[1]['landing_path'] );
	}

	/**
	 * Central consent and schema gates prevent session collection.
	 *
	 * @return void
	 */
	public function test_policy_and_schema_gates_prevent_session_collection(): void {
		add_filter( Journey_Collection_Policy::COLLECTION_ALLOWED_FILTER, static fn (): bool => false );
		self::assertNull( ( new Journey_Session_Service() )->resolve_for_activity( '/private' ) );
		self::assertSame( array(), $this->database->sessions );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );

		$GLOBALS['shurloc_test_filters'] = array();
		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( ( new Journey_Session_Service() )->resolve_for_activity( '/private' ) );
		self::assertSame( array(), $this->database->sessions );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}

	/**
	 * A session write failure does not produce a resolved activity context.
	 *
	 * @return void
	 */
	public function test_session_storage_failure_returns_null(): void {
		$this->database->fail_session_insert = true;

		self::assertNull( ( new Journey_Session_Service() )->resolve_for_activity( '/product' ) );
		self::assertCount( 1, $this->database->visitor_rows );
		self::assertSame( array(), $this->database->sessions );
		self::assertSame( 'ROLLBACK', $this->database->queries[ count( $this->database->queries ) - 1 ] );
	}
}
