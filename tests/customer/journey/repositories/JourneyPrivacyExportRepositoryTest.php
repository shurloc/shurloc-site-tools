<?php
/**
 * Tests for Customer Journey personal-data export reads.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Verify privacy export pages are complete, bounded, and user-scoped.
 */
final class JourneyPrivacyExportRepositoryTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Prepare a ready Journey schema.
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
	}

	/**
	 * Restore shared test globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_options'] = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the shared database double.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Query all record kinds through linked periods without selecting raw identity values.
	 *
	 * @return void
	 */
	public function test_query_is_user_scoped_prefixed_and_data_minimized(): void {
		$this->database->prefix = 'privacy_';

		$page = ( new Journey_Privacy_Export_Repository() )->get_user_page(
			user_id: 7,
			page: 1,
			page_size: 25
		);

		self::assertSame(
			array(
				'records'  => array(),
				'has_more' => false,
			),
			$page
		);
		$query = $this->database->prepared_queries[0];
		self::assertSame(
			array(
				'privacy_shurloc_journey_identity_periods',
				7,
				'privacy_shurloc_journey_sessions',
				'privacy_shurloc_journey_identity_periods',
				7,
				7,
				'privacy_shurloc_journey_events',
				'privacy_shurloc_journey_sessions',
				'privacy_shurloc_journey_identity_periods',
				7,
				7,
				7,
				26,
				0,
			),
			$query['args']
		);
		self::assertStringContainsString( 'p.user_id = %d', $query['query'] );
		self::assertStringContainsString( 's.user_id_at_start IS NULL OR s.user_id_at_start = %d', $query['query'] );
		self::assertStringContainsString( 'e.user_id_at_event IS NULL OR e.user_id_at_event = %d', $query['query'] );
		self::assertStringContainsString( 'ORDER BY sort_at ASC, record_rank ASC, record_id ASC', $query['query'] );
		self::assertStringNotContainsString( 'visitor_uuid', $query['query'] );
		self::assertStringNotContainsString( 'idempotency_key', $query['query'] );
	}

	/**
	 * Parse periods, sessions, and events while preserving identity-at-the-time.
	 *
	 * @return void
	 */
	public function test_parses_all_export_record_types(): void {
		$this->database->results = array(
			$this->row(
				array(
					'record_type'        => Journey_Privacy_Export_Repository::IDENTITY_PERIOD,
					'record_id'          => '4',
					'identity_period_id' => '4',
					'session_id'         => null,
					'sort_at'            => '2026-09-01 10:00:00',
					'linked_at'          => '2026-09-01 10:05:00',
					'record_ended_at'    => '2026-09-02 10:00:00',
				)
			),
			$this->row(
				array(
					'record_type'            => Journey_Privacy_Export_Repository::SESSION,
					'record_id'              => '9',
					'identity_period_id'     => '4',
					'session_id'             => '9',
					'sort_at'                => '2026-09-01 10:00:00',
					'record_ended_at'        => '2026-09-01 10:15:00',
					'began_authenticated'    => '0',
					'last_activity_at'       => '2026-09-01 10:15:00',
					'landing_path'           => '/welcome',
					'referrer_host'          => 'example.org',
					'utm_source'             => 'newsletter',
					'page_view_count'        => '2',
					'product_view_count'     => '1',
					'cart_add_count'         => '1',
					'cart_remove_count'      => '0',
					'added_quantity'         => '2.0000',
					'removed_quantity'       => '0.0000',
					'checkout_started_count' => '1',
					'order_created_count'    => '0',
					'active_ms'              => '62000',
				)
			),
			$this->row(
				array(
					'record_type'            => Journey_Privacy_Export_Repository::EVENT,
					'record_id'              => '21',
					'identity_period_id'     => '4',
					'session_id'             => '9',
					'sort_at'                => '2026-09-01 10:03:00',
					'occurred_authenticated' => '0',
					'active_ms'              => '61000',
					'event_type'             => 'product_view',
					'page_path'              => '/product/widget',
					'product_id'             => '123',
					'quantity'               => '2.0000',
					'source'                 => 'browser',
				)
			),
		);

		$page = ( new Journey_Privacy_Export_Repository() )->get_user_page( user_id: 7, page: 1 );

		self::assertNotNull( $page );
		self::assertFalse( $page['has_more'] );
		self::assertCount( 3, $page['records'] );
		self::assertSame( '2026-09-01 10:05:00', $page['records'][0]['data']['linked_at'] );
		self::assertFalse( $page['records'][1]['data']['began_authenticated'] );
		self::assertSame( 62000, $page['records'][1]['data']['active_ms'] );
		self::assertSame( 'newsletter', $page['records'][1]['data']['utm_source'] );
		self::assertFalse( $page['records'][2]['data']['occurred_authenticated'] );
		self::assertSame( 123, $page['records'][2]['data']['product_id'] );
		self::assertArrayNotHasKey( 'user_id_at_event', $page['records'][2]['data'] );
	}

	/**
	 * One extra row signals another page without escaping the requested bound.
	 *
	 * @return void
	 */
	public function test_page_offset_and_has_more_are_bounded(): void {
		$this->database->results = array(
			$this->event_row( id: '21', occurred_at: '2026-09-01 10:03:00' ),
			$this->event_row( id: '22', occurred_at: '2026-09-01 10:04:00' ),
			$this->event_row( id: '23', occurred_at: '2026-09-01 10:05:00' ),
		);

		$page = ( new Journey_Privacy_Export_Repository() )->get_user_page(
			user_id: 7,
			page: 2,
			page_size: 2
		);

		self::assertNotNull( $page );
		self::assertTrue( $page['has_more'] );
		self::assertCount( 2, $page['records'] );
		self::assertSame( array( 21, 22 ), array_column( $page['records'], 'record_id' ) );
		self::assertSame( 3, $this->database->prepared_queries[0]['args'][12] );
		self::assertSame( 2, $this->database->prepared_queries[0]['args'][13] );
	}

	/**
	 * Invalid requests and unavailable schema fail before querying storage.
	 *
	 * @return void
	 */
	public function test_invalid_inputs_and_unavailable_schema_fail_closed(): void {
		$repository = new Journey_Privacy_Export_Repository();

		self::assertNull( $repository->get_user_page( user_id: 0, page: 1 ) );
		self::assertNull( $repository->get_user_page( user_id: 7, page: 0 ) );
		self::assertNull( $repository->get_user_page( user_id: 7, page: 1, page_size: 0 ) );
		self::assertNull( $repository->get_user_page( user_id: 7, page: 1, page_size: 101 ) );
		self::assertSame( array(), $this->database->prepared_queries );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $repository->get_user_page( user_id: 7, page: 1 ) );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Unavailable and malformed database pages fail closed.
	 *
	 * @return void
	 */
	public function test_unavailable_or_malformed_rows_fail_closed(): void {
		$this->database->result_queue = array(
			null,
			array( $this->event_row( id: 'invalid', occurred_at: '2026-09-01 10:03:00' ) ),
			array( $this->row( array( 'session_id' => 'invalid' ) ) ),
			array( (object) array( 'record_type' => 'event' ) ),
		);
		$repository                   = new Journey_Privacy_Export_Repository();

		self::assertNull( $repository->get_user_page( user_id: 7, page: 1 ) );
		self::assertNull( $repository->get_user_page( user_id: 7, page: 1 ) );
		self::assertNull( $repository->get_user_page( user_id: 7, page: 1 ) );
		self::assertNull( $repository->get_user_page( user_id: 7, page: 1 ) );
	}

	/**
	 * Build a valid event row.
	 *
	 * @param string $id          Event ID.
	 * @param string $occurred_at UTC event time.
	 * @return object Event row.
	 */
	private function event_row( string $id, string $occurred_at ): object {
		return $this->row(
			array(
				'record_type'            => Journey_Privacy_Export_Repository::EVENT,
				'record_id'              => $id,
				'identity_period_id'     => '4',
				'session_id'             => '9',
				'sort_at'                => $occurred_at,
				'occurred_authenticated' => '1',
				'active_ms'              => '0',
				'event_type'             => 'page_view',
			)
		);
	}

	/**
	 * Build a normalized union row with nullable defaults.
	 *
	 * @param array $overrides Field overrides.
	 * @return object Database row.
	 * @phpstan-param array<string,mixed> $overrides
	 */
	private function row( array $overrides ): object {
		return (object) array_replace(
			array(
				'record_type'            => Journey_Privacy_Export_Repository::EVENT,
				'record_id'              => '21',
				'identity_period_id'     => '4',
				'visitor_id'             => '3',
				'session_id'             => '9',
				'sort_at'                => '2026-09-01 10:03:00',
				'linked_at'              => null,
				'record_ended_at'        => null,
				'occurred_authenticated' => '1',
				'began_authenticated'    => null,
				'last_activity_at'       => null,
				'landing_path'           => null,
				'referrer_host'          => null,
				'utm_source'             => null,
				'utm_medium'             => null,
				'utm_campaign'           => null,
				'utm_term'               => null,
				'utm_content'            => null,
				'page_view_count'        => null,
				'product_view_count'     => null,
				'cart_add_count'         => null,
				'cart_remove_count'      => null,
				'added_quantity'         => null,
				'removed_quantity'       => null,
				'checkout_started_count' => null,
				'order_created_count'    => null,
				'active_ms'              => '0',
				'event_type'             => 'page_view',
				'page_path'              => null,
				'post_id'                => null,
				'product_id'             => null,
				'variation_id'           => null,
				'quantity'               => null,
				'order_id'               => null,
				'related_object_id'      => null,
				'source'                 => null,
			),
			$overrides
		);
	}
}
