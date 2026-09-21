<?php
/**
 * Tests for Customer Journey retention scheduling.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Services\Journey_Retention_Service;
use Shurloc_Test_WPDB;

/**
 * Tests cron registration, continuation scheduling, locking, and cleanup.
 */
final class JourneyRetentionSchedulerTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Scheduler under test.
	 *
	 * @var Journey_Retention_Scheduler
	 */
	private Journey_Retention_Scheduler $scheduler;

	/**
	 * Reset hooks, cron events, filters, options, and database state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_actions']              = array();
		$GLOBALS['shurloc_test_cron_events']          = array();
		$GLOBALS['shurloc_test_cron_schedule_result'] = true;
		$GLOBALS['shurloc_test_filters']              = array();
		$GLOBALS['shurloc_test_filter_metadata']      = array();
		$GLOBALS['shurloc_test_options']              = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);

		$this->database = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = $this->database;

		$this->scheduler = new Journey_Retention_Scheduler(
			retention: new Journey_Retention_Service(),
		);
	}

	/**
	 * Restore shared test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_actions']              = array();
		$GLOBALS['shurloc_test_cron_events']          = array();
		$GLOBALS['shurloc_test_cron_schedule_result'] = true;
		$GLOBALS['shurloc_test_filters']              = array();
		$GLOBALS['shurloc_test_filter_metadata']      = array();
		$GLOBALS['shurloc_test_options']              = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Registration adds both handlers and one daily event.
	 *
	 * @return void
	 */
	public function test_registers_hooks_and_daily_event(): void {
		$before = time() + Journey_Retention_Scheduler::INITIAL_DELAY_SECONDS;
		$this->scheduler->register();
		$after = time() + Journey_Retention_Scheduler::INITIAL_DELAY_SECONDS;

		self::assertCount( 1, $GLOBALS['shurloc_test_actions'][ Journey_Retention_Scheduler::CRON_HOOK ] );
		self::assertCount( 1, $GLOBALS['shurloc_test_actions'][ Journey_Retention_Scheduler::CONTINUATION_HOOK ] );
		self::assertCount( 1, $GLOBALS['shurloc_test_cron_events'] );
		$event = $GLOBALS['shurloc_test_cron_events'][0];
		self::assertSame( Journey_Retention_Scheduler::CRON_HOOK, $event['hook'] );
		self::assertSame( 'daily', $event['recurrence'] );
		self::assertGreaterThanOrEqual( $before, $event['timestamp'] );
		self::assertLessThanOrEqual( $after, $event['timestamp'] );
	}

	/**
	 * Existing schedules are retained and schedule failures are reported.
	 *
	 * @return void
	 */
	public function test_schedule_is_idempotent_and_reports_failure(): void {
		self::assertTrue( $this->scheduler->schedule() );
		self::assertTrue( $this->scheduler->schedule() );
		self::assertCount( 1, $GLOBALS['shurloc_test_cron_events'] );

		$GLOBALS['shurloc_test_cron_events']          = array();
		$GLOBALS['shurloc_test_cron_schedule_result'] = false;
		self::assertFalse( $this->scheduler->schedule() );
		self::assertSame( array(), $GLOBALS['shurloc_test_cron_events'] );
	}

	/**
	 * A full successful batch schedules only one near-term continuation.
	 *
	 * @return void
	 */
	public function test_full_batch_schedules_one_continuation(): void {
		$this->configure_raw_event_retention();
		$this->database->query_result_queue = array(
			Journey_Retention_Service::DEFAULT_BATCH_SIZE,
			Journey_Retention_Service::DEFAULT_BATCH_SIZE,
		);

		$before = time() + Journey_Retention_Scheduler::CONTINUATION_DELAY_SECONDS;
		$this->scheduler->run();
		$this->scheduler->run();
		$after = time() + Journey_Retention_Scheduler::CONTINUATION_DELAY_SECONDS;

		self::assertCount( 1, $GLOBALS['shurloc_test_cron_events'] );
		$event = $GLOBALS['shurloc_test_cron_events'][0];
		self::assertSame( Journey_Retention_Scheduler::CONTINUATION_HOOK, $event['hook'] );
		self::assertFalse( $event['recurrence'] );
		self::assertGreaterThanOrEqual( $before, $event['timestamp'] );
		self::assertLessThanOrEqual( $after, $event['timestamp'] );
		self::assertArrayNotHasKey( Journey_Retention_Scheduler::LOCK_OPTION, $GLOBALS['shurloc_test_options'] );
	}

	/**
	 * Failed and incomplete batches do not create continuation loops.
	 *
	 * @return void
	 */
	public function test_failure_and_incomplete_batch_do_not_schedule_continuation(): void {
		$this->configure_raw_event_retention();
		$this->database->query_result_queue = array( false );
		$this->scheduler->run();
		self::assertSame( array(), $GLOBALS['shurloc_test_cron_events'] );

		$this->database->query_result_queue = array( 1 );
		$this->scheduler->run();
		self::assertSame( array(), $GLOBALS['shurloc_test_cron_events'] );
		self::assertArrayNotHasKey( Journey_Retention_Scheduler::LOCK_OPTION, $GLOBALS['shurloc_test_options'] );
	}

	/**
	 * An active lock prevents overlapping cleanup without replacing its owner.
	 *
	 * @return void
	 */
	public function test_active_lock_prevents_overlapping_cleanup(): void {
		$this->configure_raw_event_retention();
		$lock = time() . ':another-request';
		$GLOBALS['shurloc_test_options'][ Journey_Retention_Scheduler::LOCK_OPTION ] = $lock;

		$this->scheduler->run();

		self::assertSame( $lock, $GLOBALS['shurloc_test_options'][ Journey_Retention_Scheduler::LOCK_OPTION ] );
		self::assertSame( array(), $this->database->prepared_queries );
		self::assertSame( array(), $this->database->queries );
	}

	/**
	 * An unchanged stale lock is reclaimed and released after the batch.
	 *
	 * @return void
	 */
	public function test_stale_lock_is_reclaimed(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Retention_Scheduler::LOCK_OPTION ] =
			( time() - Journey_Retention_Scheduler::LOCK_TIMEOUT_SECONDS - 1 ) . ':abandoned';

		$this->scheduler->run();

		self::assertArrayNotHasKey( Journey_Retention_Scheduler::LOCK_OPTION, $GLOBALS['shurloc_test_options'] );
		self::assertCount( 2, $this->database->prepared_queries );
		self::assertSame( Journey_Retention_Scheduler::LOCK_OPTION, $this->database->prepared_queries[0]['args'][1] );
		self::assertSame( Journey_Retention_Scheduler::LOCK_OPTION, $this->database->prepared_queries[1]['args'][1] );
	}

	/**
	 * A stale-lock race cannot delete the replacement owner's lock.
	 *
	 * @return void
	 */
	public function test_stale_lock_reclamation_handles_race(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Retention_Scheduler::LOCK_OPTION ] =
			( time() - Journey_Retention_Scheduler::LOCK_TIMEOUT_SECONDS - 1 ) . ':abandoned';
		$this->database->race_on_lock_delete = true;

		$this->scheduler->run();

		self::assertSame(
			time() . ':replacement',
			$GLOBALS['shurloc_test_options'][ Journey_Retention_Scheduler::LOCK_OPTION ]
		);
		self::assertCount( 1, $this->database->prepared_queries );
	}

	/**
	 * Unscheduling removes both recurring and continuation hooks without data work.
	 *
	 * @return void
	 */
	public function test_unschedule_clears_both_event_types(): void {
		$this->scheduler->schedule();
		wp_schedule_single_event( time() + 60, Journey_Retention_Scheduler::CONTINUATION_HOOK );
		self::assertCount( 2, $GLOBALS['shurloc_test_cron_events'] );

		$this->scheduler->unschedule();

		self::assertSame( array(), $GLOBALS['shurloc_test_cron_events'] );
		self::assertSame( array(), $this->database->queries );
	}

	/**
	 * Configure a test-only raw-event period.
	 *
	 * @return void
	 */
	private function configure_raw_event_retention(): void {
		add_filter(
			Journey_Retention_Policy::RETENTION_DAYS_FILTER,
			static function ( array $days ): array {
				$days[ Journey_Retention_Policy::RAW_EVENTS ] = 30;

				return $days;
			}
		);
	}
}
