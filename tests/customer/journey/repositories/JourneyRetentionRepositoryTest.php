<?php
/**
 * Tests for Customer Journey retention cleanup storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Tests bounded SQL, dependency order, and cleanup failure handling.
 */
final class JourneyRetentionRepositoryTest extends TestCase {
	/**
	 * Test database connection.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Repository under test.
	 *
	 * @var Journey_Retention_Repository
	 */
	private Journey_Retention_Repository $repository;

	/**
	 * Provide a ready Journey schema and isolated database double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->database = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb']                 = $this->database;
		$GLOBALS['shurloc_test_options'] = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);

		$this->repository = new Journey_Retention_Repository();
	}

	/**
	 * Restore option state after each test.
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
	 * Raw event deletion uses the date index order, limit, and site prefix.
	 *
	 * @return void
	 */
	public function test_deletes_bounded_raw_event_batch(): void {
		$this->database->prefix             = 'shop_';
		$this->database->query_result_queue = array( 23 );

		self::assertSame(
			23,
			$this->repository->delete_events_before(
				cutoff_utc: '2026-01-01 00:00:00',
				batch_size: 100,
			)
		);

		$query = $this->database->prepared_queries[0];
		self::assertSame(
			array( 'shop_shurloc_journey_events', '2026-01-01 00:00:00', 100 ),
			$query['args']
		);
		self::assertStringContainsString( 'occurred_at < %s', $query['query'] );
		self::assertStringContainsString( 'ORDER BY occurred_at ASC, id ASC LIMIT %d', $query['query'] );
	}

	/**
	 * Anonymous visitor roots exclude every visitor with a user link.
	 *
	 * @return void
	 */
	public function test_deletes_anonymous_histories_in_dependency_order(): void {
		$this->queue_ids( 3, 8 );
		$this->database->query_result_queue = array( 7, 4, 2, 2 );

		self::assertSame(
			2,
			$this->repository->delete_anonymous_histories_before(
				cutoff_utc: '2026-01-01 00:00:00',
				batch_size: 25,
			)
		);

		$selection = $this->database->prepared_queries[0];
		self::assertStringContainsString( 'NOT EXISTS', $selection['query'] );
		self::assertStringContainsString( 'p.user_id IS NOT NULL', $selection['query'] );
		self::assertStringContainsString( 'LIMIT %d FOR UPDATE', $selection['query'] );
		self::assertSame(
			array(
				'wp_shurloc_journey_visitors',
				'2026-01-01 00:00:00',
				'wp_shurloc_journey_identity_periods',
				25,
			),
			$selection['args']
		);
		self::assertSame(
			array(
				'START TRANSACTION',
				'DELETE FROM %i WHERE %i IN (%d, %d)',
				'DELETE FROM %i WHERE %i IN (%d, %d)',
				'DELETE FROM %i WHERE %i IN (%d, %d)',
				'DELETE FROM %i WHERE %i IN (%d, %d)',
				'COMMIT',
			),
			$this->database->queries
		);
		self::assertSame( 'wp_shurloc_journey_events', $this->database->prepared_queries[1]['args'][0] );
		self::assertSame( 'visitor_id', $this->database->prepared_queries[1]['args'][1] );
		self::assertSame( 'wp_shurloc_journey_sessions', $this->database->prepared_queries[2]['args'][0] );
		self::assertSame( 'wp_shurloc_journey_identity_periods', $this->database->prepared_queries[3]['args'][0] );
		self::assertSame( 'wp_shurloc_journey_visitors', $this->database->prepared_queries[4]['args'][0] );
	}

	/**
	 * Identified visitor roots require a historical user association.
	 *
	 * @return void
	 */
	public function test_deletes_identified_histories_as_one_transaction(): void {
		$this->queue_ids( 11 );
		$this->database->query_result_queue = array( 3, 2, 1, 1 );

		self::assertSame(
			1,
			$this->repository->delete_identified_histories_before(
				cutoff_utc: '2026-01-01 00:00:00',
				batch_size: 10,
			)
		);

		$selection = $this->database->prepared_queries[0]['query'];
		self::assertStringContainsString( 'AND EXISTS', $selection );
		self::assertStringNotContainsString( 'NOT EXISTS', $selection );
		self::assertSame( 'COMMIT', $this->database->queries[5] );
	}

	/**
	 * Identified session cleanup removes residual events before session roots.
	 *
	 * @return void
	 */
	public function test_deletes_identified_sessions_and_residual_events(): void {
		$this->queue_ids( 20, 21 );
		$this->database->query_result_queue = array( 3, 2 );

		self::assertSame(
			2,
			$this->repository->delete_identified_sessions_before(
				cutoff_utc: '2026-02-01 00:00:00',
				batch_size: 50,
			)
		);

		$selection = $this->database->prepared_queries[0];
		self::assertStringContainsString( 's.last_activity_at < %s', $selection['query'] );
		self::assertStringContainsString( 'p.user_id IS NOT NULL', $selection['query'] );
		self::assertSame( 'session_id', $this->database->prepared_queries[1]['args'][1] );
		self::assertSame( 'id', $this->database->prepared_queries[2]['args'][1] );
		self::assertSame(
			array( 'START TRANSACTION', 'DELETE FROM %i WHERE %i IN (%d, %d)', 'DELETE FROM %i WHERE %i IN (%d, %d)', 'COMMIT' ),
			$this->database->queries
		);
	}

	/**
	 * First-touch cleanup clears every attribution field in bounded order.
	 *
	 * @return void
	 */
	public function test_clears_expired_identified_attribution(): void {
		$this->database->query_result_queue = array( 4 );

		self::assertSame(
			4,
			$this->repository->clear_identified_attribution_before(
				cutoff_utc: '2026-03-01 00:00:00',
				batch_size: 40,
			)
		);

		$query = $this->database->prepared_queries[0];
		foreach (
			array(
				'first_touch_at',
				'first_landing_path',
				'first_referrer_host',
				'first_utm_source',
				'first_utm_medium',
				'first_utm_campaign',
				'first_utm_term',
				'first_utm_content',
			) as $column
		) {
			self::assertStringContainsString( $column . ' = NULL', $query['query'] );
		}
		self::assertStringContainsString( 'p.user_id IS NOT NULL', $query['query'] );
		self::assertStringContainsString( 'ORDER BY first_touch_at ASC, id ASC LIMIT %d', $query['query'] );
	}

	/**
	 * Period cleanup preserves open and session-referenced identity periods.
	 *
	 * @return void
	 */
	public function test_deletes_only_expired_unreferenced_linked_periods(): void {
		$this->database->query_result_queue = array( 5 );

		self::assertSame(
			5,
			$this->repository->delete_unreferenced_identity_periods_before(
				cutoff_utc: '2026-04-01 00:00:00',
				batch_size: 75,
			)
		);

		$query = $this->database->prepared_queries[0];
		self::assertStringContainsString( 'user_id IS NOT NULL AND ended_at < %s', $query['query'] );
		self::assertStringContainsString( 'NOT EXISTS', $query['query'] );
		self::assertStringContainsString( 's.identity_period_id = %i.id', $query['query'] );
		self::assertStringContainsString( 'ORDER BY ended_at ASC, id ASC LIMIT %d', $query['query'] );
	}

	/**
	 * Empty root selections commit without issuing dependent deletes.
	 *
	 * @return void
	 */
	public function test_empty_root_batch_commits_without_deletes(): void {
		$this->database->result_queue = array( array() );

		self::assertSame(
			0,
			$this->repository->delete_anonymous_histories_before(
				cutoff_utc: '2026-01-01 00:00:00',
				batch_size: 10,
			)
		);
		self::assertSame( array( 'START TRANSACTION', 'COMMIT' ), $this->database->queries );
		self::assertCount( 1, $this->database->prepared_queries );
	}

	/**
	 * Invalid input and an unavailable schema perform no database work.
	 *
	 * @return void
	 */
	public function test_invalid_input_and_schema_are_rejected_before_queries(): void {
		self::assertNull( $this->repository->delete_events_before( '2026-02-30 00:00:00', 10 ) );
		self::assertNull( $this->repository->delete_events_before( '2026-01-01 00:00:00', 0 ) );
		self::assertNull(
			$this->repository->delete_events_before(
				'2026-01-01 00:00:00',
				Journey_Retention_Repository::MAX_BATCH_SIZE + 1,
			)
		);

		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $this->repository->delete_events_before( '2026-01-01 00:00:00', 10 ) );
		self::assertSame( array(), $this->database->queries );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Malformed or duplicate selected roots roll back before deleting data.
	 *
	 * @return void
	 */
	public function test_malformed_root_selection_rolls_back(): void {
		foreach ( $this->malformed_selections() as $rows ) {
			$this->reset_database_calls();
			$this->database->result_queue = array( $rows );

			self::assertNull(
				$this->repository->delete_anonymous_histories_before(
					cutoff_utc: '2026-01-01 00:00:00',
					batch_size: 10,
				)
			);
			self::assertSame( array( 'START TRANSACTION', 'ROLLBACK' ), $this->database->queries );
		}
	}

	/**
	 * Delete, final-row-count, and commit failures roll back the batch.
	 *
	 * @return void
	 */
	public function test_transaction_failures_roll_back(): void {
		$this->queue_ids( 7 );
		$this->database->query_result_queue = array( false );
		self::assertNull(
			$this->repository->delete_anonymous_histories_before( '2026-01-01 00:00:00', 10 )
		);
		self::assertSame( 'ROLLBACK', $this->database->queries[2] );

		$this->reset_database_calls();
		$this->queue_ids( 7 );
		$this->database->query_result_queue = array( 1, 1, 1, 0 );
		self::assertNull(
			$this->repository->delete_anonymous_histories_before( '2026-01-01 00:00:00', 10 )
		);
		self::assertSame( 'ROLLBACK', $this->database->queries[5] );

		$this->reset_database_calls();
		$this->queue_ids( 7 );
		$this->database->query_result_queue = array( 1, 1, 1, 1 );
		$this->database->fail_commit        = true;
		self::assertNull(
			$this->repository->delete_anonymous_histories_before( '2026-01-01 00:00:00', 10 )
		);
		self::assertSame( 'ROLLBACK', $this->database->queries[6] );
	}

	/**
	 * A failed transaction start performs no selection or deletion.
	 *
	 * @return void
	 */
	public function test_failed_transaction_start_stops_cleanup(): void {
		$this->database->fail_start = true;

		self::assertNull(
			$this->repository->delete_identified_sessions_before(
				cutoff_utc: '2026-01-01 00:00:00',
				batch_size: 10,
			)
		);
		self::assertSame( array( 'START TRANSACTION' ), $this->database->queries );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Queue ID rows for the next generic database result.
	 *
	 * @param int ...$ids Positive IDs.
	 * @return void
	 */
	private function queue_ids( int ...$ids ): void {
		$rows = array();
		foreach ( $ids as $id ) {
			$rows[] = (object) array( 'id' => (string) $id );
		}

		$this->database->result_queue = array( $rows );
	}

	/**
	 * Return unavailable, invalid, and duplicate selection results.
	 *
	 * @return list<array<int,object>|null> Malformed database results.
	 */
	private function malformed_selections(): array {
		return array(
			null,
			array( (object) array( 'id' => 'invalid' ) ),
			array( (object) array( 'id' => '7' ), (object) array( 'id' => 7 ) ),
		);
	}

	/**
	 * Reset recorded and queued calls between failure scenarios.
	 *
	 * @return void
	 */
	private function reset_database_calls(): void {
		$this->database->results            = array();
		$this->database->result_queue       = array();
		$this->database->prepared_queries   = array();
		$this->database->queries            = array();
		$this->database->query_result_queue = array();
		$this->database->fail_commit        = false;
	}
}
