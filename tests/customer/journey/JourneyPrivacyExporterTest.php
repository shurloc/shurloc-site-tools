<?php
/**
 * Tests for the Customer Journey WordPress personal-data exporter.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Privacy_Export_Repository;
use Shurloc_Test_WPDB;

/**
 * Verify exporter registration, formatting, pagination, and failures.
 */
final class JourneyPrivacyExporterTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Exporter under test.
	 *
	 * @var Journey_Privacy_Exporter
	 */
	private Journey_Privacy_Exporter $exporter;

	/**
	 * Prepare a ready schema, one test account, and clean hooks.
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
		$GLOBALS['shurloc_test_users']           = array( 7 => true );
		$GLOBALS['shurloc_test_user_data']       = array(
			7 => array( 'user_email' => 'private@example.com' ),
		);

		$this->database = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = $this->database;

		$this->exporter = new Journey_Privacy_Exporter();
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
		$GLOBALS['shurloc_test_users']           = array();
		$GLOBALS['shurloc_test_user_data']       = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore test-only database replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Registration preserves existing exporters and adds a callable Journey entry.
	 *
	 * @return void
	 */
	public function test_registers_wordpress_personal_data_exporter(): void {
		$this->exporter->register();

		$hook = 'wp_privacy_personal_data_exporters';
		self::assertCount( 1, $GLOBALS['shurloc_test_filters'][ $hook ] );
		$filter = $GLOBALS['shurloc_test_filters'][ $hook ][0];
		self::assertIsCallable( $filter );

		$other_callback = static fn (): array => array();
		$exporters      = $filter(
			array(
				'other-plugin' => array(
					'exporter_friendly_name' => 'Other plugin',
					'callback'               => $other_callback,
				),
			)
		);

		self::assertArrayHasKey( 'other-plugin', $exporters );
		self::assertArrayHasKey( Journey_Privacy_Exporter::EXPORTER_KEY, $exporters );
		$journey = $exporters[ Journey_Privacy_Exporter::EXPORTER_KEY ];
		self::assertSame( 'Customer Journey data', $journey['exporter_friendly_name'] );
		self::assertIsArray( $journey['callback'] );
		self::assertSame( $this->exporter, $journey['callback'][0] );
		self::assertSame( 'export', $journey['callback'][1] );
		self::assertSame( 10, $GLOBALS['shurloc_test_filter_metadata'][ $hook ][0]['priority'] );
	}

	/**
	 * An unknown email has no resolvable Journey identity and is complete.
	 *
	 * @return void
	 */
	public function test_unknown_email_completes_without_database_work(): void {
		self::assertSame(
			array(
				'data' => array(),
				'done' => true,
			),
			$this->exporter->export( email_address: 'unknown@example.com' )
		);
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Repository records become readable WordPress export items.
	 *
	 * @return void
	 */
	public function test_formats_identity_session_and_event_items(): void {
		$this->database->results = array(
			$this->row(
				array(
					'record_type'        => Journey_Privacy_Export_Repository::IDENTITY_PERIOD,
					'record_id'          => '4',
					'identity_period_id' => '4',
					'session_id'         => null,
					'sort_at'            => '2026-09-01 10:00:00',
					'linked_at'          => '2026-09-01 10:05:00',
				)
			),
			$this->row(
				array(
					'record_type'            => Journey_Privacy_Export_Repository::SESSION,
					'record_id'              => '9',
					'identity_period_id'     => '4',
					'session_id'             => '9',
					'sort_at'                => '2026-09-01 10:00:00',
					'began_authenticated'    => '0',
					'last_activity_at'       => '2026-09-01 10:15:00',
					'landing_path'           => '/welcome',
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
					'event_type'             => Journey_Event_Type::PRODUCT_VIEW,
					'page_path'              => '/product/widget',
					'product_id'             => '123',
				)
			),
		);

		$result = $this->exporter->export( email_address: 'PRIVATE@example.com' );

		self::assertTrue( $result['done'] );
		self::assertCount( 3, $result['data'] );
		self::assertSame( 'journey-identity-period-4', $result['data'][0]['item_id'] );
		self::assertSame( 'journey-session-9', $result['data'][1]['item_id'] );
		self::assertSame( 'journey-event-21', $result['data'][2]['item_id'] );

		$session = $this->data_by_name( item: $result['data'][1] );
		self::assertSame( 'Device 3', $session['Device reference'] );
		self::assertSame( 'No', $session['Began authenticated'] );
		self::assertSame( '62000 ms', $session['Estimated active viewing time'] );

		$event = $this->data_by_name( item: $result['data'][2] );
		self::assertSame( 'Product viewed', $event['Event'] );
		self::assertSame( 'No', $event['Occurred while authenticated'] );
		self::assertSame( '123', $event['Product ID'] );
		self::assertArrayNotHasKey( 'WordPress user ID', $event );
		self::assertArrayNotHasKey( 'Visitor UUID', $event );
	}

	/**
	 * Filtered page size and WordPress page number reach bounded storage.
	 *
	 * @return void
	 */
	public function test_filtered_page_size_controls_pagination(): void {
		add_filter(
			Journey_Privacy_Exporter::PAGE_SIZE_FILTER,
			static fn ( int $page_size ): int => min( $page_size, 1 )
		);
		$this->database->results = array(
			$this->event_row( id: '21' ),
			$this->event_row( id: '22' ),
		);

		$result = $this->exporter->export( email_address: 'private@example.com', page: 2 );

		self::assertFalse( $result['done'] );
		self::assertCount( 1, $result['data'] );
		self::assertSame( 2, $this->database->prepared_queries[0]['args'][12] );
		self::assertSame( 1, $this->database->prepared_queries[0]['args'][13] );
	}

	/**
	 * Invalid filtered page sizes fall back to the central default.
	 *
	 * @return void
	 */
	public function test_invalid_filtered_page_size_uses_default(): void {
		add_filter(
			Journey_Privacy_Exporter::PAGE_SIZE_FILTER,
			static fn (): string => 'invalid'
		);

		$this->exporter->export( email_address: 'private@example.com' );

		self::assertSame(
			Journey_Privacy_Exporter::DEFAULT_PAGE_SIZE + 1,
			$this->database->prepared_queries[0]['args'][12]
		);
	}

	/**
	 * Invalid pages and repository failures produce a visible terminal item.
	 *
	 * @return void
	 */
	public function test_invalid_page_and_repository_failure_report_export_error(): void {
		$failure = array(
			'data' => array(
				array(
					'group_id'    => Journey_Privacy_Exporter::GROUP_ID,
					'group_label' => 'Customer Journey',
					'item_id'     => 'journey-export-error',
					'data'        => array(
						array(
							'name'  => 'Export status',
							'value' => 'Customer Journey data could not be exported.',
						),
					),
				),
			),
			'done' => true,
		);

		self::assertSame( $failure, $this->exporter->export( email_address: 'private@example.com', page: 0 ) );
		self::assertSame( array(), $this->database->prepared_queries );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertSame( $failure, $this->exporter->export( email_address: 'private@example.com' ) );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Convert an export item's data list to labels keyed by name.
	 *
	 * @param array $item WordPress export item.
	 * @return array<string,string> Values keyed by field label.
	 * @phpstan-param array{data:list<array{name:string,value:string}>} $item
	 */
	private function data_by_name( array $item ): array {
		$values = array();
		foreach ( $item['data'] as $datum ) {
			$values[ $datum['name'] ] = $datum['value'];
		}

		return $values;
	}

	/**
	 * Build a valid event database row.
	 *
	 * @param string $id Event ID.
	 * @return object Event row.
	 */
	private function event_row( string $id ): object {
		return $this->row(
			array(
				'record_id'              => $id,
				'occurred_authenticated' => '1',
				'active_ms'              => '0',
				'event_type'             => Journey_Event_Type::PAGE_VIEW,
			)
		);
	}

	/**
	 * Build a normalized repository union row with nullable defaults.
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
				'event_type'             => Journey_Event_Type::PAGE_VIEW,
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
