<?php
/**
 * Tests for the Customer Journey data truncation migration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

use PHPUnit\Framework\TestCase;
use Shurloc_Test_WPDB;

/**
 * Tests complete Journey data truncation and migration state.
 */
final class JourneyDataTruncationMigrationTest extends TestCase {

	/**
	 * Database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Migration under test.
	 *
	 * @var Journey_Data_Truncation_Migration
	 */
	private Journey_Data_Truncation_Migration $migration;

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

		$this->migration = new Journey_Data_Truncation_Migration();
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
	 * Every Journey table is truncated in child-to-parent order.
	 *
	 * @return void
	 */
	public function test_run_truncates_all_journey_tables(): void {
		$this->database->query_result_queue = array_fill( 0, 5, 0 );

		$result = $this->migration->run();

		self::assertSame(
			array(
				'truncated' => 5,
				'errors'    => 0,
			),
			$result
		);
		self::assertSame(
			array(
				'shop_shurloc_journey_cart_links',
				'shop_shurloc_journey_events',
				'shop_shurloc_journey_sessions',
				'shop_shurloc_journey_identity_periods',
				'shop_shurloc_journey_visitors',
			),
			array_map(
				static fn ( array $prepared ): string =>
					(string) $prepared['args'][0],
				$this->database->prepared_queries
			)
		);
		self::assertSame(
			array_fill( 0, 5, 'TRUNCATE TABLE %i' ),
			$this->database->queries
		);
		self::assertSame(
			Journey_Data_Truncation_Migration::VERSION,
			$GLOBALS['shurloc_test_options']
				[ Journey_Data_Truncation_Migration::LAST_RUN_VERSION_OPTION ]
		);
		self::assertGreaterThan(
			0,
			$this->migration->get_last_run()
		);
	}

	/**
	 * A database failure stops before parent tables and is not recorded as a run.
	 *
	 * @return void
	 */
	public function test_run_stops_on_first_failure(): void {
		$this->database->query_result_queue = array( 0, 0, false );

		self::assertSame(
			array(
				'truncated' => 2,
				'errors'    => 1,
			),
			$this->migration->run()
		);
		self::assertCount( 3, $this->database->queries );
		self::assertArrayNotHasKey(
			Journey_Data_Truncation_Migration::LAST_RUN_OPTION,
			$GLOBALS['shurloc_test_options']
		);
		self::assertArrayNotHasKey(
			Journey_Data_Truncation_Migration::LAST_RUN_VERSION_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * An unavailable schema fails closed without running database statements.
	 *
	 * @return void
	 */
	public function test_run_requires_current_journey_schema(): void {
		$GLOBALS['shurloc_test_options'] = array();

		self::assertSame(
			array(
				'truncated' => 0,
				'errors'    => 1,
			),
			$this->migration->run()
		);
		self::assertSame( array(), $this->database->queries );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Active locks prevent a second Journey maintenance migration.
	 *
	 * @return void
	 */
	public function test_acquire_lock_rejects_active_lock(): void {
		$GLOBALS['shurloc_test_options']
			[ Journey_Data_Truncation_Migration::LOCK_OPTION ] = time();

		self::assertTrue( $this->migration->is_locked() );
		self::assertFalse( $this->migration->acquire_lock() );
	}

	/**
	 * A stale Journey maintenance lock can be replaced.
	 *
	 * @return void
	 */
	public function test_acquire_lock_replaces_stale_lock(): void {
		$GLOBALS['shurloc_test_options']
			[ Journey_Data_Truncation_Migration::LOCK_OPTION ] = time() - 901;

		self::assertTrue( $this->migration->acquire_lock() );
		self::assertTrue( $this->migration->is_locked() );
	}

	/**
	 * The Journey maintenance lock can be released.
	 *
	 * @return void
	 */
	public function test_release_lock_removes_lock(): void {
		self::assertTrue( $this->migration->acquire_lock() );

		$this->migration->release_lock();

		self::assertFalse( $this->migration->is_locked() );
		self::assertArrayNotHasKey(
			Journey_Data_Truncation_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}
}
