<?php
/**
 * Tests for the Customer Journey schema migrator.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

use PHPUnit\Framework\TestCase;
use Shurloc_Test_WPDB;

/**
 * Tests migration versioning, locking, and verification.
 */
final class JourneySchemaMigratorTest extends TestCase {
	/**
	 * Database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options']          = array();
		$GLOBALS['shurloc_journey_dbdelta_calls'] = array();
		$GLOBALS['shurloc_journey_dbdelta_apply'] = true;

		$this->database = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = $this->database;
	}

	/**
	 * Restore test globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_options']          = array();
		$GLOBALS['shurloc_journey_dbdelta_calls'] = array();
		$GLOBALS['shurloc_journey_dbdelta_apply'] = true;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Construct the migrator with a simulated dbDelta boundary.
	 *
	 * @return Journey_Schema_Migrator Migrator under test.
	 */
	private function create_migrator(): Journey_Schema_Migrator {
		return new Journey_Schema_Migrator(
			schema_updater: function ( string $statement ): void {
				$GLOBALS['shurloc_journey_dbdelta_calls'][] = $statement;

				if ( $GLOBALS['shurloc_journey_dbdelta_apply'] ) {
					$this->database->install_table( sql: $statement );
				}
			},
		);
	}

	/**
	 * Install the V1 schema and record its completed version.
	 *
	 * @return void
	 */
	private function install_v1_schema(): void {
		$statements = Journey_Schema_V1::get_create_table_statements(
			table_prefix: $this->database->prefix,
			charset_collate: $this->database->get_charset_collate(),
		);

		foreach ( $statements as $statement ) {
			$this->database->install_table( sql: $statement );
		}

		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 1;
	}

	/**
	 * Install the V1 and V2 schemas and record their completed version.
	 *
	 * @return void
	 */
	private function install_v2_schema(): void {
		$this->install_v1_schema();

		$statements = Journey_Schema_V2::get_create_table_statements(
			table_prefix: $this->database->prefix,
			charset_collate: $this->database->get_charset_collate(),
		);

		foreach ( $statements as $statement ) {
			$this->database->install_table( sql: $statement );
		}

		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 2;
	}

	/**
	 * Verify a fresh install creates and verifies every table.
	 *
	 * @return void
	 */
	public function test_fresh_install_creates_five_tables_and_stores_version(): void {
		$migrator = $this->create_migrator();

		self::assertTrue( $migrator->migrate() );
		self::assertSame( 3, $migrator->get_installed_version() );
		self::assertTrue( $migrator->is_ready() );
		self::assertCount( 6, $GLOBALS['shurloc_journey_dbdelta_calls'] );
		self::assertSame(
			array(
				'wp_shurloc_journey_visitors',
				'wp_shurloc_journey_identity_periods',
				'wp_shurloc_journey_sessions',
				'wp_shurloc_journey_events',
				'wp_shurloc_journey_cart_links',
			),
			array_keys( $this->database->tables )
		);
		foreach ( $this->database->tables as $table ) {
			self::assertSame( 'InnoDB', $table['engine'] );
		}
		self::assertArrayNotHasKey(
			Journey_Schema_Migrator::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify an up-to-date installation performs no schema work.
	 *
	 * @return void
	 */
	public function test_repeated_migration_is_idempotent(): void {
		$migrator = $this->create_migrator();
		self::assertTrue( $migrator->migrate() );
		$GLOBALS['shurloc_journey_dbdelta_calls'] = array();

		self::assertTrue( $migrator->migrate() );
		self::assertSame( array(), $GLOBALS['shurloc_journey_dbdelta_calls'] );
		self::assertSame( 3, $migrator->get_installed_version() );
	}

	/**
	 * Verify another request's active lock prevents concurrent upgrades.
	 *
	 * @return void
	 */
	public function test_active_lock_prevents_migration(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::LOCK_OPTION ] =
			time() . ':another-request';

		$migrator = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertSame( 0, $migrator->get_installed_version() );
		self::assertSame( array(), $GLOBALS['shurloc_journey_dbdelta_calls'] );
	}

	/**
	 * Verify stale locks may be reclaimed without losing other option data.
	 *
	 * @return void
	 */
	public function test_stale_lock_is_reclaimed(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::LOCK_OPTION ] =
			( time() - 901 ) . ':abandoned';

		$migrator = $this->create_migrator();

		self::assertTrue( $migrator->migrate() );
		self::assertSame( 3, $migrator->get_installed_version() );
		self::assertArrayNotHasKey(
			Journey_Schema_Migrator::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify an existing V1 installation applies V2 and V3 in order.
	 *
	 * @return void
	 */
	public function test_v1_installation_applies_v2_and_v3(): void {
		$this->install_v1_schema();
		$this->database->charset_collate_calls = 0;
		$migrator                              = $this->create_migrator();

		self::assertTrue( $migrator->migrate() );
		self::assertSame( 3, $migrator->get_installed_version() );
		self::assertTrue( $migrator->is_ready() );
		self::assertCount( 2, $GLOBALS['shurloc_journey_dbdelta_calls'] );
		self::assertStringStartsWith(
			'CREATE TABLE wp_shurloc_journey_cart_links',
			$GLOBALS['shurloc_journey_dbdelta_calls'][0]
		);
		self::assertStringStartsWith(
			'CREATE TABLE wp_shurloc_journey_sessions',
			$GLOBALS['shurloc_journey_dbdelta_calls'][1]
		);
		self::assertSame( 2, $this->database->charset_collate_calls );
	}

	/**
	 * Verify V3 evolves sessions and backfills totals without double-counting products.
	 *
	 * @return void
	 */
	public function test_v2_installation_applies_only_v3_and_backfills_event_count(): void {
		$this->install_v2_schema();
		$this->database->sessions[20]          = array(
			'id'                     => 20,
			'visitor_id'             => 12,
			'identity_period_id'     => 7,
			'user_id_at_start'       => null,
			'began_authenticated'    => 0,
			'started_at'             => '2026-09-01 12:00:00',
			'last_activity_at'       => '2026-09-01 12:30:00',
			'ended_at'               => null,
			'landing_path'           => '/products',
			'referrer_host'          => null,
			'utm_source'             => null,
			'utm_medium'             => null,
			'utm_campaign'           => null,
			'utm_term'               => null,
			'utm_content'            => null,
			'page_view_count'        => 3,
			'product_view_count'     => 2,
			'cart_add_count'         => 4,
			'cart_remove_count'      => 1,
			'added_quantity'         => '4.0000',
			'removed_quantity'       => '1.0000',
			'checkout_started_count' => 2,
			'order_created_count'    => 1,
			'active_ms'              => 120000,
		);
		$this->database->charset_collate_calls = 0;
		$migrator                              = $this->create_migrator();

		self::assertTrue( $migrator->migrate() );
		self::assertSame( 3, $migrator->get_installed_version() );
		self::assertTrue( $migrator->is_ready() );
		self::assertCount( 1, $GLOBALS['shurloc_journey_dbdelta_calls'] );
		self::assertStringStartsWith(
			'CREATE TABLE wp_shurloc_journey_sessions',
			$GLOBALS['shurloc_journey_dbdelta_calls'][0]
		);
		self::assertArrayHasKey( 'event_count', $this->database->sessions[20] );
		self::assertSame( 11, $this->database->sessions[20]['event_count'] );
		self::assertSame( 1, $this->database->charset_collate_calls );
	}

	/**
	 * Verify a failed V3 backfill leaves V2 installed for a safe retry.
	 *
	 * @return void
	 */
	public function test_failed_v3_backfill_does_not_advance_version(): void {
		$this->install_v2_schema();
		$this->database->fail_event_count_backfill = true;
		$migrator                                  = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertSame( 2, $migrator->get_installed_version() );
		self::assertFalse( $migrator->is_ready() );
		self::assertSame( 'migration_failed', $migrator->get_failure_code() );

		$this->database->fail_event_count_backfill = false;
		self::assertTrue( $migrator->migrate() );
		self::assertSame( 3, $migrator->get_installed_version() );
		self::assertSame( '', $migrator->get_failure_code() );
	}

	/**
	 * Verify V2 does not advance when its new table is unavailable.
	 *
	 * @return void
	 */
	public function test_failed_v2_migration_keeps_v1_version(): void {
		$this->install_v1_schema();
		$GLOBALS['shurloc_journey_dbdelta_apply'] = false;
		$migrator                                 = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertSame( 1, $migrator->get_installed_version() );
		self::assertFalse( $migrator->is_ready() );
		self::assertSame( 'migration_failed', $migrator->get_failure_code() );
		self::assertArrayNotHasKey(
			'wp_shurloc_journey_cart_links',
			$this->database->tables
		);
	}

	/**
	 * Verify V2 checks the existing V1 schema before storing version two.
	 *
	 * @return void
	 */
	public function test_v2_verifies_combined_schema_before_advancing(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 1;
		$migrator = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertSame( 1, $migrator->get_installed_version() );
		self::assertArrayHasKey(
			'wp_shurloc_journey_cart_links',
			$this->database->tables
		);
		self::assertSame( 'migration_failed', $migrator->get_failure_code() );
	}

	/**
	 * Verify a changed stale lock cannot be deleted or taken over.
	 *
	 * @return void
	 */
	public function test_stale_lock_reclamation_handles_a_race(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::LOCK_OPTION ] =
			( time() - 901 ) . ':abandoned';
		$this->database->race_on_lock_delete                                     = true;

		$migrator = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertSame( 0, $migrator->get_installed_version() );
		self::assertSame(
			time() . ':replacement',
			$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::LOCK_OPTION ]
		);
	}

	/**
	 * Verify a failed dbDelta leaves the schema unready and version unchanged.
	 *
	 * @return void
	 */
	public function test_missing_tables_do_not_advance_version(): void {
		$GLOBALS['shurloc_journey_dbdelta_apply'] = false;
		$migrator                                 = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertSame( 0, $migrator->get_installed_version() );
		self::assertFalse( $migrator->is_ready() );
		self::assertSame( 'migration_failed', $migrator->get_failure_code() );
		self::assertArrayNotHasKey(
			Journey_Schema_Migrator::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify incomplete indexes prevent advancing the schema version.
	 *
	 * @return void
	 */
	public function test_missing_index_does_not_advance_version(): void {
		$this->database->missing_index = 'idempotency_key';
		$migrator                      = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertCount( 4, $this->database->tables );
		self::assertSame( 0, $migrator->get_installed_version() );
		self::assertSame( 'migration_failed', $migrator->get_failure_code() );

		$this->database->missing_index = '';
		self::assertTrue( $migrator->migrate() );
		self::assertSame( '', $migrator->get_failure_code() );
	}

	/**
	 * Verify a missing column leaves the version unchanged for a later retry.
	 *
	 * @return void
	 */
	public function test_missing_column_does_not_advance_version(): void {
		$this->database->missing_column = 'identity_period_id';
		$migrator                       = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertSame( 0, $migrator->get_installed_version() );
		self::assertCount( 4, $this->database->tables );
	}

	/**
	 * Verify a nontransactional table blocks the migration after dbDelta runs.
	 *
	 * @return void
	 */
	public function test_nontransactional_table_does_not_advance_version(): void {
		$this->database->table_engine_overrides['wp_shurloc_journey_events'] = 'MyISAM';
		$migrator = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertCount( 4, $this->database->tables );
		self::assertSame( 0, $migrator->get_installed_version() );
		self::assertFalse( $migrator->is_ready() );
		self::assertSame( 'migration_failed', $migrator->get_failure_code() );
		self::assertArrayNotHasKey(
			Journey_Schema_Migrator::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify a failed engine inspection also leaves the schema unavailable.
	 *
	 * @return void
	 */
	public function test_missing_table_status_does_not_advance_version(): void {
		$this->database->fail_table_status = true;

		$migrator = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertSame( 0, $migrator->get_installed_version() );
		self::assertFalse( $migrator->is_ready() );
		self::assertSame( 'migration_failed', $migrator->get_failure_code() );
	}

	/**
	 * Verify a required unique key cannot silently become a regular index.
	 *
	 * @return void
	 */
	public function test_non_unique_idempotency_index_does_not_advance_version(): void {
		$this->database->non_unique_index = 'idempotency_key';
		$migrator                         = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertSame( 0, $migrator->get_installed_version() );
		self::assertSame( 'migration_failed', $migrator->get_failure_code() );
	}

	/**
	 * Verify custom WordPress table prefixes reach dbDelta and verification.
	 *
	 * @return void
	 */
	public function test_custom_table_prefix_is_used(): void {
		$this->database->prefix  = 'store_42_';
		$this->database->options = 'store_42_options';
		$migrator                = $this->create_migrator();

		self::assertTrue( $migrator->migrate() );
		self::assertArrayHasKey(
			'store_42_shurloc_journey_events',
			$this->database->tables
		);
		self::assertArrayHasKey(
			'store_42_shurloc_journey_cart_links',
			$this->database->tables
		);
		self::assertStringStartsWith(
			'CREATE TABLE store_42_shurloc_journey_visitors',
			$GLOBALS['shurloc_journey_dbdelta_calls'][0]
		);
		self::assertContains(
			array(
				'query' => 'SHOW TABLE STATUS WHERE Name = %s',
				'args'  => array( 'store_42_shurloc_journey_events' ),
			),
			$this->database->prepared_queries
		);
		self::assertContains(
			array(
				'query' => 'SHOW TABLE STATUS WHERE Name = %s',
				'args'  => array( 'store_42_shurloc_journey_cart_links' ),
			),
			$this->database->prepared_queries
		);
	}

	/**
	 * Verify future migrations are selected in version order.
	 *
	 * @return void
	 */
	public function test_future_versions_are_sequential(): void {
		self::assertSame(
			array( 1, 2, 3 ),
			Journey_Schema_Migrator::get_pending_versions(
				installed_version: 0,
				target_version: 3,
			)
		);
		self::assertSame(
			array( 2, 3 ),
			Journey_Schema_Migrator::get_pending_versions(
				installed_version: 1,
				target_version: 3,
			)
		);
		self::assertSame(
			array(),
			Journey_Schema_Migrator::get_pending_versions(
				installed_version: 3,
				target_version: 3,
			)
		);
	}

	/**
	 * Verify older plugin code will not run against a newer schema.
	 *
	 * @return void
	 */
	public function test_newer_installed_version_is_not_downgraded(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 4;
		$migrator = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertSame( 4, $migrator->get_installed_version() );
		self::assertFalse( $migrator->is_ready() );
		self::assertSame( 'newer_schema', $migrator->get_failure_code() );
		self::assertSame( array(), $GLOBALS['shurloc_journey_dbdelta_calls'] );
	}
}
