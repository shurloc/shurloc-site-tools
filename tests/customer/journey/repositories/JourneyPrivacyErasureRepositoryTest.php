<?php
/**
 * Tests for Customer Journey personal-data erasure storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Verify bounded, user-specific erasure and shared-browser preservation.
 */
final class JourneyPrivacyErasureRepositoryTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Repository under test.
	 *
	 * @var Journey_Privacy_Erasure_Repository
	 */
	private Journey_Privacy_Erasure_Repository $repository;

	/**
	 * Prepare a ready Journey schema and shared database double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options'] = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$this->database                  = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = $this->database;

		$this->repository = new Journey_Privacy_Erasure_Repository();
	}

	/**
	 * Restore shared test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_options'] = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore test-only database replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Invalid inputs and an unavailable schema perform no database work.
	 *
	 * @return void
	 */
	public function test_invalid_inputs_and_schema_are_rejected(): void {
		self::assertNull( $this->repository->erase_user_batch( user_id: 0, batch_size: 10 ) );
		self::assertNull( $this->repository->erase_user_batch( user_id: 7, batch_size: 0 ) );
		self::assertNull(
			$this->repository->erase_user_batch(
				user_id: 7,
				batch_size: Journey_Privacy_Erasure_Repository::MAX_BATCH_SIZE + 1,
			)
		);

		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $this->repository->erase_user_batch( user_id: 7, batch_size: 10 ) );
		self::assertSame( array(), $this->database->prepared_queries );
		self::assertSame( array(), $this->database->queries );
	}

	/**
	 * A user with no linked period is already fully erased.
	 *
	 * @return void
	 */
	public function test_no_linked_period_completes_without_transaction(): void {
		$this->database->prefix  = 'privacy_';
		$this->database->results = array();

		self::assertSame(
			$this->expected_result(),
			$this->repository->erase_user_batch( user_id: 7, batch_size: 10 )
		);

		self::assertSame( array(), $this->database->queries );
		self::assertSame(
			array( 'privacy_shurloc_journey_identity_periods', 7 ),
			$this->database->prepared_queries[0]['args']
		);
		self::assertStringContainsString( 'ORDER BY id ASC LIMIT 1', $this->database->prepared_queries[0]['query'] );
	}

	/**
	 * Event erasure is bounded to the selected user's locked identity period.
	 *
	 * @return void
	 */
	public function test_events_are_removed_before_sessions_and_period(): void {
		$this->queue_locked_period(
			additional_results: array(
				array( (object) array( 'id' => '21' ), (object) array( 'id' => '22' ) ),
			)
		);
		$this->database->query_result_queue = array( 2 );

		self::assertSame(
			$this->expected_result( events_removed: 2, has_more: true ),
			$this->repository->erase_user_batch( user_id: 7, batch_size: 2 )
		);

		self::assertSame( array( 'START TRANSACTION', 'DELETE FROM %i WHERE id IN (%d, %d)', 'COMMIT' ), $this->database->queries );
		self::assertSame(
			array( 'wp_shurloc_journey_events', 'wp_shurloc_journey_sessions', 4, 2 ),
			$this->database->prepared_queries[3]['args']
		);
		self::assertSame(
			array( 'wp_shurloc_journey_events', 21, 22 ),
			$this->database->prepared_queries[4]['args']
		);
		self::assertCount( 5, $this->database->prepared_queries );
	}

	/**
	 * Sessions are removed only after the selected period has no events.
	 *
	 * @return void
	 */
	public function test_sessions_are_removed_after_events_are_empty(): void {
		$this->queue_locked_period(
			additional_results: array(
				array(),
				array( (object) array( 'id' => '31' ), (object) array( 'id' => '32' ) ),
			)
		);
		$this->database->query_result_queue = array( 2 );
		$this->database->cart_links         = array(
			1 => array(
				'id'              => 1,
				'cart_token_hash' => str_repeat( 'a', 64 ),
				'visitor_id'      => 3,
				'session_id'      => 31,
				'linked_at'       => '2026-09-24 12:00:00',
				'last_seen_at'    => '2026-09-24 12:00:00',
			),
			2 => array(
				'id'              => 2,
				'cart_token_hash' => str_repeat( 'b', 64 ),
				'visitor_id'      => 9,
				'session_id'      => 99,
				'linked_at'       => '2026-09-24 13:00:00',
				'last_seen_at'    => '2026-09-24 13:00:00',
			),
		);

		self::assertSame(
			$this->expected_result( sessions_removed: 2, has_more: true ),
			$this->repository->erase_user_batch( user_id: 7, batch_size: 2 )
		);

		self::assertSame(
			array( 'wp_shurloc_journey_sessions', 4, 2 ),
			$this->database->prepared_queries[4]['args']
		);
		self::assertSame(
			array( 'wp_shurloc_journey_cart_links', 31, 32 ),
			$this->database->prepared_queries[5]['args']
		);
		self::assertSame(
			array( 'wp_shurloc_journey_sessions', 31, 32 ),
			$this->database->prepared_queries[6]['args']
		);
		self::assertSame(
			array(
				'START TRANSACTION',
				'DELETE FROM %i WHERE session_id IN (%d, %d)',
				'DELETE FROM %i WHERE id IN (%d, %d)',
				'COMMIT',
			),
			$this->database->queries
		);
		self::assertArrayNotHasKey( 1, $this->database->cart_links );
		self::assertArrayHasKey( 2, $this->database->cart_links );
		self::assertCount( 7, $this->database->prepared_queries );
	}

	/**
	 * Empty periods are removed while shared visitor history remains protected.
	 *
	 * @return void
	 */
	public function test_period_removal_clears_attribution_and_only_deletes_orphan_visitor(): void {
		$this->database->prefix = 'privacy_';
		$this->queue_locked_period(
			additional_results: array( array(), array() )
		);
		$this->database->query_result_queue = array( 1, 1, 0 );

		self::assertSame(
			$this->expected_result( periods_removed: 1, has_more: true ),
			$this->repository->erase_user_batch( user_id: 7, batch_size: 10 )
		);

		$period_delete = $this->database->prepared_queries[5];
		self::assertSame( array( 'privacy_shurloc_journey_identity_periods', 4 ), $period_delete['args'] );

		$attribution_clear = $this->database->prepared_queries[6];
		self::assertSame( array( 'privacy_shurloc_journey_visitors', 3 ), $attribution_clear['args'] );
		self::assertStringContainsString( 'first_touch_at = NULL', $attribution_clear['query'] );
		self::assertStringContainsString( 'first_utm_content = NULL', $attribution_clear['query'] );

		$orphan_delete = $this->database->prepared_queries[7];
		self::assertSame(
			array(
				'privacy_shurloc_journey_visitors',
				3,
				'privacy_shurloc_journey_identity_periods',
				'privacy_shurloc_journey_sessions',
				'privacy_shurloc_journey_events',
			),
			$orphan_delete['args']
		);
		self::assertStringContainsString( 'NOT EXISTS (SELECT 1 FROM %i p', $orphan_delete['query'] );
		self::assertStringContainsString( 'NOT EXISTS (SELECT 1 FROM %i s', $orphan_delete['query'] );
		self::assertStringContainsString( 'NOT EXISTS (SELECT 1 FROM %i e', $orphan_delete['query'] );
	}

	/**
	 * A period removed between the probe and lock is treated as concurrent progress.
	 *
	 * @return void
	 */
	public function test_concurrently_removed_period_requests_another_check(): void {
		$this->database->result_queue = array(
			array( $this->period_row() ),
			array( (object) array( 'id' => '3' ) ),
			array(),
		);

		self::assertSame(
			$this->expected_result( has_more: true ),
			$this->repository->erase_user_batch( user_id: 7, batch_size: 10 )
		);
		self::assertSame( array( 'START TRANSACTION', 'COMMIT' ), $this->database->queries );
	}

	/**
	 * Malformed roots and dependent selections fail closed and roll back.
	 *
	 * @return void
	 */
	public function test_malformed_rows_fail_closed(): void {
		$this->database->results = array(
			(object) array(
				'id'         => 'bad',
				'visitor_id' => '3',
			),
		);
		self::assertNull( $this->repository->erase_user_batch( user_id: 7, batch_size: 10 ) );
		self::assertSame( array(), $this->database->queries );

		$this->reset_calls();
		$this->database->result_queue = array(
			array( $this->period_row() ),
			array( (object) array( 'id' => '3' ) ),
			array( $this->period_row() ),
			null,
		);
		self::assertNull( $this->repository->erase_user_batch( user_id: 7, batch_size: 10 ) );
		self::assertSame( 'ROLLBACK', $this->database->queries[1] );
	}

	/**
	 * Transaction and mutation failures roll back without reporting success.
	 *
	 * @return void
	 */
	public function test_transaction_failures_roll_back(): void {
		$this->database->results    = array( $this->period_row() );
		$this->database->fail_start = true;
		self::assertNull( $this->repository->erase_user_batch( user_id: 7, batch_size: 10 ) );
		self::assertSame( array( 'START TRANSACTION' ), $this->database->queries );

		$this->reset_calls();
		$this->queue_locked_period(
			additional_results: array(
				array( (object) array( 'id' => '21' ) ),
			)
		);
		$this->database->query_result_queue = array( 0 );
		self::assertNull( $this->repository->erase_user_batch( user_id: 7, batch_size: 10 ) );
		self::assertSame( 'ROLLBACK', $this->database->queries[2] );

		$this->reset_calls();
		$this->queue_locked_period(
			additional_results: array(
				array(),
				array( (object) array( 'id' => '31' ) ),
			)
		);
		$this->database->cart_links[1]      = array(
			'id'              => 1,
			'cart_token_hash' => str_repeat( 'c', 64 ),
			'visitor_id'      => 3,
			'session_id'      => 31,
			'linked_at'       => '2026-09-24 14:00:00',
			'last_seen_at'    => '2026-09-24 14:00:00',
		);
		$this->database->query_result_queue = array( 0 );
		self::assertNull( $this->repository->erase_user_batch( user_id: 7, batch_size: 10 ) );
		self::assertSame( 'ROLLBACK', $this->database->queries[3] );
		self::assertArrayHasKey( 1, $this->database->cart_links );

		$this->reset_calls();
		$this->queue_locked_period(
			additional_results: array(
				array(),
				array( (object) array( 'id' => '31' ) ),
			)
		);
		$this->database->fail_cart_link_delete = true;
		self::assertNull( $this->repository->erase_user_batch( user_id: 7, batch_size: 10 ) );
		self::assertSame( 'ROLLBACK', $this->database->queries[2] );

		$this->reset_calls();
		$this->queue_locked_period(
			additional_results: array( array(), array() )
		);
		$this->database->query_result_queue = array( 1, false );
		self::assertNull( $this->repository->erase_user_batch( user_id: 7, batch_size: 10 ) );
		self::assertSame( 'ROLLBACK', $this->database->queries[3] );

		$this->reset_calls();
		$this->queue_locked_period(
			additional_results: array( array(), array() )
		);
		$this->database->query_result_queue = array( 1, 0, 0 );
		$this->database->fail_commit        = true;
		self::assertNull( $this->repository->erase_user_batch( user_id: 7, batch_size: 10 ) );
		self::assertSame( 'ROLLBACK', $this->database->queries[5] );
	}

	/**
	 * Queue a period probe, visitor lock, period lock, and dependent selections.
	 *
	 * @param list<array<int,object>|null> $additional_results Results after the locks.
	 * @return void
	 */
	private function queue_locked_period( array $additional_results ): void {
		$this->database->result_queue = array_merge(
			array(
				array( $this->period_row() ),
				array( (object) array( 'id' => '3' ) ),
				array( $this->period_row() ),
			),
			$additional_results
		);
	}

	/**
	 * Return a valid selected identity period.
	 *
	 * @return object Database row.
	 */
	private function period_row(): object {
		return (object) array(
			'id'         => '4',
			'visitor_id' => '3',
		);
	}

	/**
	 * Build an expected batch result.
	 *
	 * @param int  $events_removed   Removed events.
	 * @param int  $sessions_removed Removed sessions.
	 * @param int  $periods_removed  Removed periods.
	 * @param bool $has_more         Whether another call is required.
	 * @return array<string,int|bool> Result.
	 */
	private function expected_result(
		int $events_removed = 0,
		int $sessions_removed = 0,
		int $periods_removed = 0,
		bool $has_more = false
	): array {
		return array(
			'events_removed'   => $events_removed,
			'sessions_removed' => $sessions_removed,
			'periods_removed'  => $periods_removed,
			'has_more'         => $has_more,
		);
	}

	/**
	 * Reset database calls and queues between failure scenarios.
	 *
	 * @return void
	 */
	private function reset_calls(): void {
		$this->database->results            = array();
		$this->database->result_queue       = array();
		$this->database->prepared_queries   = array();
		$this->database->queries            = array();
		$this->database->query_result_queue = array();
		$this->database->cart_links         = array();

		$this->database->fail_start            = false;
		$this->database->fail_commit           = false;
		$this->database->fail_cart_link_delete = false;
	}
}
