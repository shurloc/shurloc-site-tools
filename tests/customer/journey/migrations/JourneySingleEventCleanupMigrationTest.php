<?php
/**
 * Tests for the Customer Journey single-event cleanup migration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

use PHPUnit\Framework\TestCase;
use Shurloc_Test_WPDB;

/**
 * Tests dependency-safe cleanup of zero-time, single-event journeys.
 */
final class JourneySingleEventCleanupMigrationTest extends TestCase {

	/**
	 * Database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Migration under test.
	 *
	 * @var Journey_Single_Event_Cleanup_Migration
	 */
	private Journey_Single_Event_Cleanup_Migration $migration;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options'] = array(
			Journey_Schema_Migrator::VERSION_OPTION =>
				Journey_Schema_Migrator::CURRENT_VERSION,
		);

		$this->database         = new Shurloc_Test_WPDB();
		$this->database->prefix = 'shop_';

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = $this->database;

		$this->migration = new Journey_Single_Event_Cleanup_Migration();
	}

	/**
	 * Restore test globals.
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
	 * Matching sessions and their dependents are deleted in one transaction.
	 *
	 * @return void
	 */
	public function test_run_deletes_matching_sessions_and_dependents(): void {
		$this->database->result_queue       = array(
			array(
				(object) array( 'id' => '20' ),
				(object) array( 'id' => '21' ),
			),
		);
		$this->database->query_result_queue = array( 2, 2, 2 );

		self::assertSame(
			array(
				'deleted' => 2,
				'errors'  => 0,
			),
			$this->migration->run()
		);
		self::assertSame(
			array(
				'START TRANSACTION',
				'DELETE FROM %i WHERE %i IN (%d, %d)',
				'DELETE FROM %i WHERE %i IN (%d, %d)',
				'DELETE FROM %i WHERE %i IN (%d, %d)',
				'COMMIT',
			),
			$this->database->queries
		);

		$selection = $this->database->prepared_queries[0];
		self::assertStringContainsString(
			'WHERE event_count = 1 AND active_ms = 0',
			$selection['query']
		);
		self::assertStringContainsString(
			'ORDER BY id ASC LIMIT %d FOR UPDATE',
			$selection['query']
		);
		self::assertSame(
			array(
				'shop_shurloc_journey_sessions',
				Journey_Single_Event_Cleanup_Migration::BATCH_SIZE,
			),
			$selection['args']
		);
		self::assertSame(
			array(
				'shop_shurloc_journey_cart_links',
				'session_id',
				20,
				21,
			),
			$this->database->prepared_queries[1]['args']
		);
		self::assertSame(
			array(
				'shop_shurloc_journey_events',
				'session_id',
				20,
				21,
			),
			$this->database->prepared_queries[2]['args']
		);
		self::assertSame(
			array(
				'shop_shurloc_journey_sessions',
				'id',
				20,
				21,
			),
			$this->database->prepared_queries[3]['args']
		);
		self::assertSame(
			Journey_Single_Event_Cleanup_Migration::VERSION,
			$this->migration->get_last_run_version()
		);
		self::assertGreaterThan( 0, $this->migration->get_last_run() );
	}

	/**
	 * An empty selection commits as a successful no-op.
	 *
	 * @return void
	 */
	public function test_run_with_no_matches_returns_zero(): void {
		$this->database->result_queue = array( array() );

		self::assertSame(
			array(
				'deleted' => 0,
				'errors'  => 0,
			),
			$this->migration->run()
		);
		self::assertSame(
			array( 'START TRANSACTION', 'COMMIT' ),
			$this->database->queries
		);
	}

	/**
	 * A full batch causes the migration to continue until no matches remain.
	 *
	 * @return void
	 */
	public function test_run_continues_after_full_batch(): void {
		$rows = array();

		for (
			$id = 1;
			$id <= Journey_Single_Event_Cleanup_Migration::BATCH_SIZE;
			++$id
		) {
			$rows[] = (object) array( 'id' => (string) $id );
		}

		$this->database->result_queue       = array( $rows, array() );
		$this->database->query_result_queue = array(
			0,
			0,
			Journey_Single_Event_Cleanup_Migration::BATCH_SIZE,
		);
		$delete_query                       = 'DELETE FROM %i WHERE %i IN (' .
			implode(
				', ',
				array_fill(
					0,
					Journey_Single_Event_Cleanup_Migration::BATCH_SIZE,
					'%d'
				)
			) .
			')';

		self::assertSame(
			array(
				'deleted' =>
					Journey_Single_Event_Cleanup_Migration::BATCH_SIZE,
				'errors'  => 0,
			),
			$this->migration->run()
		);
		self::assertSame(
			array(
				'START TRANSACTION',
				$delete_query,
				$delete_query,
				$delete_query,
				'COMMIT',
				'START TRANSACTION',
				'COMMIT',
			),
			$this->database->queries
		);
	}

	/**
	 * A dependency failure rolls back the batch and records no successful run.
	 *
	 * @return void
	 */
	public function test_run_rolls_back_when_dependency_deletion_fails(): void {
		$this->database->result_queue       = array(
			array( (object) array( 'id' => '20' ) ),
		);
		$this->database->query_result_queue = array( false );

		self::assertSame(
			array(
				'deleted' => 0,
				'errors'  => 1,
			),
			$this->migration->run()
		);
		self::assertSame(
			'ROLLBACK',
			$this->database->queries[ count( $this->database->queries ) - 1 ]
		);
		self::assertArrayNotHasKey(
			Journey_Single_Event_Cleanup_Migration::LAST_RUN_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * A changed session count rolls back the entire batch.
	 *
	 * @return void
	 */
	public function test_run_rolls_back_when_session_count_changes(): void {
		$this->database->result_queue       = array(
			array( (object) array( 'id' => '20' ) ),
		);
		$this->database->query_result_queue = array( 1, 1, 0 );

		self::assertSame(
			array(
				'deleted' => 0,
				'errors'  => 1,
			),
			$this->migration->run()
		);
		self::assertSame(
			'ROLLBACK',
			$this->database->queries[ count( $this->database->queries ) - 1 ]
		);
	}

	/**
	 * Malformed selections fail closed inside the transaction.
	 *
	 * @return void
	 */
	public function test_run_rejects_malformed_session_ids(): void {
		$this->database->result_queue = array(
			array(
				(object) array( 'id' => '20' ),
				(object) array( 'id' => '20' ),
			),
		);

		self::assertSame(
			array(
				'deleted' => 0,
				'errors'  => 1,
			),
			$this->migration->run()
		);
		self::assertSame(
			array( 'START TRANSACTION', 'ROLLBACK' ),
			$this->database->queries
		);
	}

	/**
	 * An unavailable schema fails closed without database work.
	 *
	 * @return void
	 */
	public function test_run_requires_current_journey_schema(): void {
		$GLOBALS['shurloc_test_options'] = array();

		self::assertSame(
			array(
				'deleted' => 0,
				'errors'  => 1,
			),
			$this->migration->run()
		);
		self::assertSame( array(), $this->database->queries );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Both destructive Journey migrations use the same maintenance lock.
	 *
	 * @return void
	 */
	public function test_migrations_share_maintenance_lock(): void {
		$GLOBALS['shurloc_test_options']
			[ Journey_Data_Truncation_Migration::LOCK_OPTION ] = time();

		self::assertTrue( $this->migration->is_locked() );
		self::assertFalse( $this->migration->acquire_lock() );

		$this->migration->release_lock();

		self::assertFalse( $this->migration->is_locked() );
	}
}
