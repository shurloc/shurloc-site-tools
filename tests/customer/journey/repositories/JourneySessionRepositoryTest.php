<?php
/**
 * Tests for Customer Journey session storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Tests serialized session creation, continuation, and timeout boundaries.
 */
final class JourneySessionRepositoryTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Prepare a ready schema and one existing visitor.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options'] = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);

		$this->database               = new Shurloc_Test_WPDB();
		$this->database->visitors[12] = true;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = $this->database;
	}

	/**
	 * Restore shared test globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_options'] = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * A first activity creates a session with start identity and attribution.
	 *
	 * @return void
	 */
	public function test_first_activity_creates_session_with_start_snapshot(): void {
		$this->database->prefix = 'shop_';

		$id = ( new Journey_Session_Repository() )->resolve_current(
			visitor_id: 12,
			identity_period_id: 4,
			user_id_at_start: 37,
			observed_at: '2026-09-16 12:00:00',
			timeout_seconds: 1800,
			landing_path: '/products/widget',
			referrer_host: 'example.org',
			utm_source: 'newsletter',
			utm_medium: 'email',
			utm_campaign: 'autumn',
			utm_term: 'widget',
			utm_content: 'button',
		);

		self::assertSame( 1, $id );
		self::assertSame( 12, $this->database->sessions[1]['visitor_id'] );
		self::assertSame( 4, $this->database->sessions[1]['identity_period_id'] );
		self::assertSame( 37, $this->database->sessions[1]['user_id_at_start'] );
		self::assertSame( 1, $this->database->sessions[1]['began_authenticated'] );
		self::assertSame( '2026-09-16 12:00:00', $this->database->sessions[1]['started_at'] );
		self::assertSame( '2026-09-16 12:00:00', $this->database->sessions[1]['last_activity_at'] );
		self::assertNull( $this->database->sessions[1]['ended_at'] );
		self::assertSame( '/products/widget', $this->database->sessions[1]['landing_path'] );
		self::assertSame( 'example.org', $this->database->sessions[1]['referrer_host'] );
		self::assertSame( 'newsletter', $this->database->sessions[1]['utm_source'] );
		self::assertSame( 'email', $this->database->sessions[1]['utm_medium'] );
		self::assertSame( 'autumn', $this->database->sessions[1]['utm_campaign'] );
		self::assertSame( 'widget', $this->database->sessions[1]['utm_term'] );
		self::assertSame( 'button', $this->database->sessions[1]['utm_content'] );
		self::assertSame( 'shop_shurloc_journey_visitors', $this->database->prepared_queries[0]['args'][0] );
		self::assertSame( 'shop_shurloc_journey_sessions', $this->database->prepared_queries[1]['args'][0] );
		self::assertStringContainsString( 'FOR UPDATE', $this->database->prepared_queries[0]['query'] );
		self::assertStringContainsString( 'LIMIT 2 FOR UPDATE', $this->database->prepared_queries[1]['query'] );
		self::assertSame( 'shop_shurloc_journey_sessions', $this->database->insert_calls[0]['table'] );
		self::assertCount( 13, $this->database->insert_calls[0]['formats'] );
		self::assertSame( array( 'START TRANSACTION', 'COMMIT' ), $this->database->queries );
	}

	/**
	 * Later activity extends one session without changing its start identity.
	 *
	 * @return void
	 */
	public function test_activity_within_timeout_reuses_session_without_rewriting_start(): void {
		$repository = new Journey_Session_Repository();
		self::assertSame( 1, $this->resolve( repository: $repository ) );

		self::assertSame(
			1,
			$repository->resolve_current(
				visitor_id: 12,
				identity_period_id: 9,
				user_id_at_start: 37,
				observed_at: '2026-09-16 12:29:59',
				timeout_seconds: 1800,
				landing_path: '/later',
			)
		);

		self::assertSame( 1, $this->resolve( repository: $repository, observed_at: '2026-09-16 12:20:00' ) );
		self::assertCount( 1, $this->database->sessions );
		self::assertSame( '2026-09-16 12:29:59', $this->database->sessions[1]['last_activity_at'] );
		self::assertSame( 3, $this->database->sessions[1]['identity_period_id'] );
		self::assertNull( $this->database->sessions[1]['user_id_at_start'] );
		self::assertSame( 0, $this->database->sessions[1]['began_authenticated'] );
		self::assertNull( $this->database->sessions[1]['landing_path'] );
		self::assertCount( 1, $this->database->insert_calls );
	}

	/**
	 * The exact inactivity boundary starts a new session and ends the old one.
	 *
	 * @return void
	 */
	public function test_timeout_boundary_starts_new_session_without_phantom_activity(): void {
		$repository = new Journey_Session_Repository();
		self::assertSame( 1, $this->resolve( repository: $repository ) );

		self::assertSame(
			2,
			$repository->resolve_current(
				visitor_id: 12,
				identity_period_id: 9,
				user_id_at_start: 37,
				observed_at: '2026-09-16 12:30:00',
				timeout_seconds: 1800,
				landing_path: '/return',
			)
		);

		self::assertSame( '2026-09-16 12:00:00', $this->database->sessions[1]['ended_at'] );
		self::assertSame( '2026-09-16 12:30:00', $this->database->sessions[2]['started_at'] );
		self::assertSame( 9, $this->database->sessions[2]['identity_period_id'] );
		self::assertSame( 37, $this->database->sessions[2]['user_id_at_start'] );
		self::assertSame( 1, $this->database->sessions[2]['began_authenticated'] );
		self::assertSame( '/return', $this->database->sessions[2]['landing_path'] );
		self::assertCount( 2, $this->database->insert_calls );
	}

	/**
	 * The repository uses the caller's timeout without choosing a default.
	 *
	 * @return void
	 */
	public function test_caller_supplied_timeout_controls_session_boundary(): void {
		$repository = new Journey_Session_Repository();
		self::assertSame( 1, $this->resolve( repository: $repository ) );

		self::assertSame(
			2,
			$repository->resolve_current(
				visitor_id: 12,
				identity_period_id: 3,
				user_id_at_start: null,
				observed_at: '2026-09-16 12:01:00',
				timeout_seconds: 60,
			)
		);
		self::assertCount( 2, $this->database->sessions );
	}

	/**
	 * Two browser visitors cannot share an open session.
	 *
	 * @return void
	 */
	public function test_different_visitors_get_different_sessions(): void {
		$this->database->visitors[13] = true;
		$repository                   = new Journey_Session_Repository();

		self::assertSame( 1, $this->resolve( repository: $repository ) );
		self::assertSame(
			2,
			$repository->resolve_current(
				visitor_id: 13,
				identity_period_id: 5,
				user_id_at_start: null,
				observed_at: '2026-09-16 12:01:00',
				timeout_seconds: 1800,
			)
		);
		self::assertSame( 12, $this->database->sessions[1]['visitor_id'] );
		self::assertSame( 13, $this->database->sessions[2]['visitor_id'] );
	}

	/**
	 * Invalid input and an unavailable schema never start a transaction.
	 *
	 * @return void
	 */
	public function test_invalid_inputs_and_schema_gate_prevent_database_work(): void {
		$repository = new Journey_Session_Repository();

		self::assertNull( $repository->resolve_current( 0, 3, null, '2026-09-16 12:00:00', 1800 ) );
		self::assertNull( $repository->resolve_current( 12, 0, null, '2026-09-16 12:00:00', 1800 ) );
		self::assertNull( $repository->resolve_current( 12, 3, 0, '2026-09-16 12:00:00', 1800 ) );
		self::assertNull( $repository->resolve_current( 12, 3, null, '2026-09-16 12:00:00', 0 ) );
		self::assertNull( $repository->resolve_current( 12, 3, null, 'invalid', 1800 ) );
		self::assertNull( $repository->resolve_current( 12, 3, null, '2026-02-30 12:00:00', 1800 ) );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $this->resolve( repository: $repository ) );
		self::assertSame( array(), $this->database->queries );
		self::assertSame( array(), $this->database->prepared_queries );
		self::assertSame( array(), $this->database->insert_calls );
	}

	/**
	 * Missing visitors and duplicate open rows fail without inserting sessions.
	 *
	 * @return void
	 */
	public function test_missing_visitor_and_ambiguous_open_rows_fail_closed(): void {
		$repository = new Journey_Session_Repository();
		self::assertNull( $repository->resolve_current( 99, 3, null, '2026-09-16 12:00:00', 1800 ) );
		self::assertSame( array(), $this->database->sessions );

		self::assertSame( 1, $this->resolve( repository: $repository ) );
		$duplicate = $this->database->sessions[1];

		$duplicate['id'] = 2;

		$this->database->sessions[2] = $duplicate;

		self::assertNull( $this->resolve( repository: $repository, observed_at: '2026-09-16 12:01:00' ) );
		self::assertCount( 2, $this->database->sessions );
		self::assertCount( 1, $this->database->insert_calls );
		self::assertSame( 'ROLLBACK', $this->database->queries[ count( $this->database->queries ) - 1 ] );
	}

	/**
	 * An older request cannot move a session before its start or regress time.
	 *
	 * @return void
	 */
	public function test_out_of_order_and_invalid_stored_times_fail_closed(): void {
		$repository = new Journey_Session_Repository();
		self::assertSame( 1, $this->resolve( repository: $repository ) );
		self::assertNull( $this->resolve( repository: $repository, observed_at: '2026-09-16 11:59:59' ) );
		self::assertSame( '2026-09-16 12:00:00', $this->database->sessions[1]['last_activity_at'] );

		$this->database->sessions[1]['last_activity_at'] = 'invalid';
		self::assertNull( $this->resolve( repository: $repository, observed_at: '2026-09-16 12:01:00' ) );
		self::assertSame( 'invalid', $this->database->sessions[1]['last_activity_at'] );
	}

	/**
	 * Database failures leave any existing session state intact.
	 *
	 * @return void
	 */
	public function test_start_lookup_insert_and_commit_failures_roll_back(): void {
		$repository = new Journey_Session_Repository();

		$this->database->fail_start = true;
		self::assertNull( $this->resolve( repository: $repository ) );

		$this->database->fail_start          = false;
		$this->database->fail_session_select = true;
		self::assertNull( $this->resolve( repository: $repository ) );

		$this->database->fail_session_select = false;
		$this->database->fail_session_insert = true;
		self::assertNull( $this->resolve( repository: $repository ) );

		$this->database->fail_session_insert = false;
		$this->database->fail_commit         = true;
		self::assertNull( $this->resolve( repository: $repository ) );
		self::assertSame( array(), $this->database->sessions );
		self::assertSame( 'ROLLBACK', $this->database->queries[ count( $this->database->queries ) - 1 ] );
	}

	/**
	 * Failed activity updates do not advance a stored session.
	 *
	 * @return void
	 */
	public function test_activity_update_failure_rolls_back(): void {
		$repository = new Journey_Session_Repository();
		self::assertSame( 1, $this->resolve( repository: $repository ) );
		$this->database->fail_session_update = true;

		self::assertNull( $this->resolve( repository: $repository, observed_at: '2026-09-16 12:01:00' ) );
		self::assertSame( '2026-09-16 12:00:00', $this->database->sessions[1]['last_activity_at'] );
		self::assertSame( 'ROLLBACK', $this->database->queries[ count( $this->database->queries ) - 1 ] );
	}

	/**
	 * A failed close or replacement insert restores the original open row.
	 *
	 * @return void
	 */
	public function test_timeout_close_and_replacement_failures_roll_back(): void {
		$repository = new Journey_Session_Repository();
		self::assertSame( 1, $this->resolve( repository: $repository ) );
		$this->database->fail_session_close = true;

		self::assertNull( $this->resolve( repository: $repository, observed_at: '2026-09-16 12:30:00' ) );
		self::assertNull( $this->database->sessions[1]['ended_at'] );

		$this->database->fail_session_close  = false;
		$this->database->fail_session_insert = true;
		self::assertNull( $this->resolve( repository: $repository, observed_at: '2026-09-16 12:30:00' ) );
		self::assertCount( 1, $this->database->sessions );
		self::assertNull( $this->database->sessions[1]['ended_at'] );
	}

	/**
	 * Resolve a baseline anonymous session for one visitor.
	 *
	 * @param Journey_Session_Repository $repository Repository under test.
	 * @param string                     $observed_at Server time.
	 * @return int|null Session ID.
	 */
	private function resolve(
		Journey_Session_Repository $repository,
		string $observed_at = '2026-09-16 12:00:00'
	): ?int {
		return $repository->resolve_current(
			visitor_id: 12,
			identity_period_id: 3,
			user_id_at_start: null,
			observed_at: $observed_at,
			timeout_seconds: 1800,
		);
	}
}
