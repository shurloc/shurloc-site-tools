<?php
/**
 * Tests for Customer Journey identity periods.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Tests identity transitions, historical linkage, and atomic storage.
 */
final class JourneyIdentityPeriodRepositoryTest extends TestCase {
	/**
	 * Database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Prepare a ready schema and one visitor.
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
	 * Anonymous requests share one open period until identity changes.
	 *
	 * @return void
	 */
	public function test_first_anonymous_period_is_reused(): void {
		$repository = new Journey_Identity_Period_Repository();

		self::assertSame( 1, $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:00:00' ) );
		self::assertSame( 1, $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:01:00' ) );
		self::assertCount( 1, $this->database->periods );
		self::assertNull( $this->database->periods[1]['user_id'] );
		self::assertNull( $this->database->periods[1]['linked_at'] );
		self::assertNull( $this->database->periods[1]['ended_at'] );
		self::assertCount( 1, $this->database->insert_calls );
		self::assertSame( array( '%d', '%d', '%s', '%s' ), $this->database->insert_calls[0]['formats'] );
	}

	/**
	 * An authenticated first visit records the account and link time.
	 *
	 * @return void
	 */
	public function test_first_authenticated_period_records_link_time(): void {
		self::assertSame(
			1,
			( new Journey_Identity_Period_Repository() )->ensure_current(
				visitor_id: 12,
				user_id: 37,
				observed_at: '2026-09-16 12:00:00'
			)
		);

		self::assertSame( 37, $this->database->periods[1]['user_id'] );
		self::assertSame( '2026-09-16 12:00:00', $this->database->periods[1]['linked_at'] );
		self::assertNull( $this->database->periods[1]['ended_at'] );
	}

	/**
	 * Login links the earlier anonymous period and starts a new auth period.
	 *
	 * @return void
	 */
	public function test_login_links_and_closes_anonymous_history(): void {
		$repository = new Journey_Identity_Period_Repository();
		self::assertSame( 1, $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:00:00' ) );
		self::assertSame( 2, $repository->ensure_current( visitor_id: 12, user_id: 37, observed_at: '2026-09-16 12:05:00' ) );
		self::assertSame( 2, $repository->ensure_current( visitor_id: 12, user_id: 37, observed_at: '2026-09-16 12:06:00' ) );

		self::assertSame( '2026-09-16 12:00:00', $this->database->periods[1]['started_at'] );
		self::assertSame( 37, $this->database->periods[1]['user_id'] );
		self::assertSame( '2026-09-16 12:05:00', $this->database->periods[1]['linked_at'] );
		self::assertSame( '2026-09-16 12:05:00', $this->database->periods[1]['ended_at'] );
		self::assertSame( 37, $this->database->periods[2]['user_id'] );
		self::assertSame( '2026-09-16 12:05:00', $this->database->periods[2]['started_at'] );
		self::assertNull( $this->database->periods[2]['ended_at'] );
		self::assertCount( 2, $this->database->insert_calls );
	}

	/**
	 * Logout and a later different login preserve separate account periods.
	 *
	 * @return void
	 */
	public function test_logout_and_account_switch_preserve_period_history(): void {
		$repository = new Journey_Identity_Period_Repository();
		self::assertSame( 1, $repository->ensure_current( visitor_id: 12, user_id: 37, observed_at: '2026-09-16 12:00:00' ) );
		self::assertSame( 2, $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:05:00' ) );
		self::assertSame( 3, $repository->ensure_current( visitor_id: 12, user_id: 38, observed_at: '2026-09-16 12:10:00' ) );

		self::assertSame( 37, $this->database->periods[1]['user_id'] );
		self::assertSame( '2026-09-16 12:05:00', $this->database->periods[1]['ended_at'] );
		self::assertSame( 38, $this->database->periods[2]['user_id'] );
		self::assertSame( '2026-09-16 12:10:00', $this->database->periods[2]['linked_at'] );
		self::assertSame( 38, $this->database->periods[3]['user_id'] );
		self::assertNull( $this->database->periods[3]['ended_at'] );
	}

	/**
	 * Stale requests cannot replace a period that began more recently.
	 *
	 * @return void
	 */
	public function test_out_of_order_transition_rolls_back(): void {
		$repository = new Journey_Identity_Period_Repository();
		self::assertSame( 1, $repository->ensure_current( visitor_id: 12, user_id: 37, observed_at: '2026-09-16 12:05:00' ) );

		self::assertNull( $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:04:00' ) );
		self::assertCount( 1, $this->database->periods );
		self::assertNull( $this->database->periods[1]['ended_at'] );
		self::assertSame( 'ROLLBACK', $this->database->queries[ count( $this->database->queries ) - 1 ] );
	}

	/**
	 * A login in the same stored second still opens a new identity period.
	 *
	 * @return void
	 */
	public function test_same_second_login_can_transition_identity(): void {
		$repository = new Journey_Identity_Period_Repository();

		self::assertSame( 1, $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:05:00' ) );
		self::assertSame( 2, $repository->ensure_current( visitor_id: 12, user_id: 37, observed_at: '2026-09-16 12:05:00' ) );
		self::assertSame( '2026-09-16 12:05:00', $this->database->periods[1]['ended_at'] );
		self::assertSame( 37, $this->database->periods[1]['user_id'] );
		self::assertSame( '2026-09-16 12:05:00', $this->database->periods[2]['started_at'] );
	}

	/**
	 * A missing or unavailable schema never reaches identity table storage.
	 *
	 * @return void
	 */
	public function test_unready_schema_and_invalid_inputs_do_not_query_database(): void {
		$GLOBALS['shurloc_test_options'] = array();
		$repository                      = new Journey_Identity_Period_Repository();

		self::assertNull( $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:00:00' ) );
		self::assertSame( array(), $this->database->queries );

		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = Journey_Schema_Migrator::CURRENT_VERSION;
		self::assertNull( $repository->ensure_current( visitor_id: 0, user_id: null, observed_at: '2026-09-16 12:00:00' ) );
		self::assertNull( $repository->ensure_current( visitor_id: 12, user_id: 0, observed_at: '2026-09-16 12:00:00' ) );
		self::assertNull( $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '' ) );
		self::assertSame( array(), $this->database->queries );
	}

	/**
	 * An unknown visitor and ambiguous open periods fail closed.
	 *
	 * @return void
	 */
	public function test_unknown_visitor_and_multiple_open_periods_fail_closed(): void {
		$repository = new Journey_Identity_Period_Repository();

		self::assertNull( $repository->ensure_current( visitor_id: 99, user_id: null, observed_at: '2026-09-16 12:00:00' ) );
		self::assertSame( array(), $this->database->periods );

		$this->database->periods[1] = array(
			'id'         => 1,
			'visitor_id' => 12,
			'user_id'    => null,
			'started_at' => '2026-09-16 12:00:00',
			'linked_at'  => null,
			'ended_at'   => null,
		);
		$this->database->periods[2] = array(
			'id'         => 2,
			'visitor_id' => 12,
			'user_id'    => null,
			'started_at' => '2026-09-16 12:01:00',
			'linked_at'  => null,
			'ended_at'   => null,
		);

		self::assertNull( $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:02:00' ) );
		self::assertCount( 2, $this->database->periods );
		self::assertSame( array(), $this->database->insert_calls );
	}

	/**
	 * A failed insert after closing a period restores the earlier open row.
	 *
	 * @return void
	 */
	public function test_failed_insert_rolls_back_period_transition(): void {
		$repository = new Journey_Identity_Period_Repository();
		self::assertSame( 1, $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:00:00' ) );
		$this->database->fail_insert = true;

		self::assertNull( $repository->ensure_current( visitor_id: 12, user_id: 37, observed_at: '2026-09-16 12:05:00' ) );
		self::assertNull( $this->database->periods[1]['user_id'] );
		self::assertNull( $this->database->periods[1]['linked_at'] );
		self::assertNull( $this->database->periods[1]['ended_at'] );
		self::assertCount( 1, $this->database->periods );
	}

	/**
	 * A failed close leaves the anonymous period open and unlinked.
	 *
	 * @return void
	 */
	public function test_failed_close_rolls_back_period_transition(): void {
		$repository = new Journey_Identity_Period_Repository();
		self::assertSame( 1, $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:00:00' ) );
		$this->database->fail_close = true;

		self::assertNull( $repository->ensure_current( visitor_id: 12, user_id: 37, observed_at: '2026-09-16 12:05:00' ) );
		self::assertCount( 1, $this->database->periods );
		self::assertNull( $this->database->periods[1]['user_id'] );
		self::assertNull( $this->database->periods[1]['ended_at'] );
	}

	/**
	 * Transaction and lookup errors leave current identity data intact.
	 *
	 * @return void
	 */
	public function test_transaction_failures_leave_no_new_period(): void {
		$repository = new Journey_Identity_Period_Repository();

		$this->database->fail_start = true;
		self::assertNull( $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:00:00' ) );
		self::assertSame( array(), $this->database->periods );

		$this->database->fail_start  = false;
		$this->database->fail_select = true;
		self::assertNull( $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:00:00' ) );
		self::assertSame( array(), $this->database->periods );

		$this->database->fail_select = false;
		$this->database->fail_commit = true;
		self::assertNull( $repository->ensure_current( visitor_id: 12, user_id: null, observed_at: '2026-09-16 12:00:00' ) );
		self::assertSame( array(), $this->database->periods );
	}

	/**
	 * Visitor and period queries use the active WordPress table prefix.
	 *
	 * @return void
	 */
	public function test_table_prefix_is_used_for_both_identity_tables(): void {
		$this->database->prefix = 'shop_';

		self::assertSame(
			1,
			( new Journey_Identity_Period_Repository() )->ensure_current(
				visitor_id: 12,
				user_id: null,
				observed_at: '2026-09-16 12:00:00'
			)
		);
		self::assertSame( 'shop_shurloc_journey_visitors', $this->database->prepared_queries[0]['args'][0] );
		self::assertSame( 'shop_shurloc_journey_identity_periods', $this->database->prepared_queries[1]['args'][0] );
		self::assertSame( 'shop_shurloc_journey_identity_periods', $this->database->insert_calls[0]['table'] );
	}

	/**
	 * One account may have separate visitor identities on different browsers.
	 *
	 * @return void
	 */
	public function test_same_user_can_have_periods_for_multiple_visitors(): void {
		$this->database->visitors[13] = true;
		$repository                   = new Journey_Identity_Period_Repository();

		self::assertSame( 1, $repository->ensure_current( visitor_id: 12, user_id: 37, observed_at: '2026-09-16 12:00:00' ) );
		self::assertSame( 2, $repository->ensure_current( visitor_id: 13, user_id: 37, observed_at: '2026-09-16 12:01:00' ) );
		self::assertSame( 12, $this->database->periods[1]['visitor_id'] );
		self::assertSame( 13, $this->database->periods[2]['visitor_id'] );
		self::assertSame( 37, $this->database->periods[1]['user_id'] );
		self::assertSame( 37, $this->database->periods[2]['user_id'] );
	}
}
