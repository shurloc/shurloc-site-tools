<?php
/**
 * Tests for Customer Journey retention cleanup orchestration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Services;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Journey_Retention_Policy;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Tests policy gating, cutoff calculation, order, and continuation results.
 */
final class JourneyRetentionServiceTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Service under test.
	 *
	 * @var Journey_Retention_Service
	 */
	private Journey_Retention_Service $service;

	/**
	 * Prepare a ready schema with no configured retention periods.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['shurloc_test_options']         = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);

		$this->database = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = $this->database;

		$this->service = new Journey_Retention_Service();
	}

	/**
	 * Restore shared test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['shurloc_test_options']         = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Unapproved default periods result in no database cleanup.
	 *
	 * @return void
	 */
	public function test_unset_retention_periods_skip_every_operation(): void {
		self::assertSame(
			$this->empty_result(),
			$this->service->run_batch( now: $this->now() )
		);
		self::assertSame( array(), $this->database->prepared_queries );
		self::assertSame( array(), $this->database->queries );
	}

	/**
	 * Configured scopes use UTC cutoffs and the required dependency order.
	 *
	 * @return void
	 */
	public function test_configured_scopes_run_in_dependency_order_with_utc_cutoffs(): void {
		$this->configure_periods( anonymous_days: 10, event_days: 20, identified_days: 30 );
		$this->database->result_queue       = array( array(), array(), array() );
		$this->database->query_result_queue = array( 0, 0, 0 );

		self::assertSame( $this->empty_result(), $this->service->run_batch( now: $this->now() ) );

		self::assertCount( 6, $this->database->prepared_queries );
		self::assertSame( '2026-09-01 12:30:00', $this->database->prepared_queries[0]['args'][1] );
		self::assertStringContainsString( 'DELETE FROM %i WHERE occurred_at', $this->database->prepared_queries[0]['query'] );

		for ( $index = 1; $index <= 4; ++$index ) {
			self::assertSame( '2026-08-22 12:30:00', $this->database->prepared_queries[ $index ]['args'][1] );
		}
		self::assertStringContainsString( 'SELECT s.id', $this->database->prepared_queries[1]['query'] );
		self::assertStringContainsString( 'UPDATE %i SET first_touch_at = NULL', $this->database->prepared_queries[2]['query'] );
		self::assertStringContainsString( 'SELECT v.id', $this->database->prepared_queries[3]['query'] );
		self::assertStringContainsString( 'AND EXISTS', $this->database->prepared_queries[3]['query'] );
		self::assertStringContainsString( 'DELETE FROM %i', $this->database->prepared_queries[4]['query'] );

		self::assertSame( '2026-09-11 12:30:00', $this->database->prepared_queries[5]['args'][1] );
		self::assertStringContainsString( 'NOT EXISTS', $this->database->prepared_queries[5]['query'] );
	}

	/**
	 * A configured batch size is applied consistently and reports continuation.
	 *
	 * @return void
	 */
	public function test_filtered_batch_size_sets_limit_and_has_more(): void {
		$this->configure_periods( event_days: 20 );
		add_filter(
			Journey_Retention_Service::BATCH_SIZE_FILTER,
			static fn (): int => 7
		);
		$this->database->query_result_queue = array( 7 );

		$result = $this->service->run_batch( now: $this->now() );

		self::assertTrue( $result['success'] );
		self::assertTrue( $result['has_more'] );
		self::assertSame( 7, $result['events_deleted'] );
		self::assertSame( 7, $this->database->prepared_queries[0]['args'][2] );
	}

	/**
	 * Invalid batch configuration falls back to the central safe default.
	 *
	 * @return void
	 */
	public function test_invalid_batch_size_uses_default(): void {
		$this->configure_periods( event_days: 20 );
		add_filter(
			Journey_Retention_Service::BATCH_SIZE_FILTER,
			static fn (): string => '7'
		);
		$this->database->query_result_queue = array( 0 );

		$this->service->run_batch( now: $this->now() );

		self::assertSame(
			Journey_Retention_Service::DEFAULT_BATCH_SIZE,
			$this->database->prepared_queries[0]['args'][2]
		);
	}

	/**
	 * A repository failure stops later scopes and marks the batch failed.
	 *
	 * @return void
	 */
	public function test_first_repository_failure_stops_batch(): void {
		$this->configure_periods( anonymous_days: 10, event_days: 20, identified_days: 30 );
		$this->database->query_result_queue = array( false );

		$expected            = $this->empty_result();
		$expected['success'] = false;

		self::assertSame( $expected, $this->service->run_batch( now: $this->now() ) );
		self::assertCount( 1, $this->database->prepared_queries );
		self::assertCount( 1, $this->database->queries );
	}

	/**
	 * Completed counts survive a later failure so retry state is observable.
	 *
	 * @return void
	 */
	public function test_partial_result_is_returned_when_later_operation_fails(): void {
		$this->configure_periods( event_days: 20, identified_days: 30 );
		$this->database->query_result_queue = array( 2 );
		$this->database->result_queue       = array( null );

		$result = $this->service->run_batch( now: $this->now() );

		self::assertFalse( $result['success'] );
		self::assertFalse( $result['has_more'] );
		self::assertSame( 2, $result['events_deleted'] );
		self::assertSame( 0, $result['identified_sessions_deleted'] );
		self::assertSame( array( 'DELETE FROM %i WHERE occurred_at < %s ORDER BY occurred_at ASC, id ASC LIMIT %d', 'START TRANSACTION', 'ROLLBACK' ), $this->database->queries );
		self::assertCount( 2, $this->database->prepared_queries );
	}

	/**
	 * Configure selected retention scopes without choosing production defaults.
	 *
	 * @param int|null $anonymous_days  Anonymous-history days.
	 * @param int|null $event_days      Raw-event days.
	 * @param int|null $identified_days Identified-history days.
	 * @return void
	 */
	private function configure_periods(
		?int $anonymous_days = null,
		?int $event_days = null,
		?int $identified_days = null
	): void {
		add_filter(
			Journey_Retention_Policy::RETENTION_DAYS_FILTER,
			static function ( array $days ) use ( $anonymous_days, $event_days, $identified_days ): array {
				$days[ Journey_Retention_Policy::ANONYMOUS_HISTORY ]  = $anonymous_days;
				$days[ Journey_Retention_Policy::RAW_EVENTS ]         = $event_days;
				$days[ Journey_Retention_Policy::IDENTIFIED_HISTORY ] = $identified_days;

				return $days;
			}
		);
	}

	/**
	 * Return a non-UTC instant for cutoff conversion tests.
	 *
	 * @return DateTimeImmutable Fixed current time.
	 */
	private function now(): DateTimeImmutable {
		return new DateTimeImmutable(
			'2026-09-21 05:30:00',
			new DateTimeZone( 'America/Los_Angeles' )
		);
	}

	/**
	 * Return the successful no-op result.
	 *
	 * @return array{success:bool,has_more:bool,events_deleted:int,identified_sessions_deleted:int,identified_attribution_cleared:int,identified_histories_deleted:int,identity_periods_deleted:int,anonymous_histories_deleted:int} Empty result.
	 */
	private function empty_result(): array {
		return array(
			'success'                        => true,
			'has_more'                       => false,
			'events_deleted'                 => 0,
			'identified_sessions_deleted'    => 0,
			'identified_attribution_cleared' => 0,
			'identified_histories_deleted'   => 0,
			'identity_periods_deleted'       => 0,
			'anonymous_histories_deleted'    => 0,
		);
	}
}
