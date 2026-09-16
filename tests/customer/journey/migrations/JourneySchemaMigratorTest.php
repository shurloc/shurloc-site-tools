<?php
/**
 * Tests for the Customer Journey schema migrator.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shurloc_Test_WPDB;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Keep the focused database double with its test in one review unit.

	/**
	 * Small database double for dbDelta and schema verification.
	 */
final class Shurloc_Journey_Schema_Test_WPDB {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * WordPress options table name.
	 *
	 * @var string
	 */
	public string $options = 'wp_options';

	/**
	 * Installed table definitions keyed by full name.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	public array $tables = array();

	/**
	 * Index name to omit from simulated SHOW INDEX results.
	 *
	 * @var string
	 */
	public string $missing_index = '';

	/**
	 * Column name to omit from simulated SHOW COLUMNS results.
	 *
	 * @var string
	 */
	public string $missing_column = '';

	/**
	 * Unique index to present as non-unique.
	 *
	 * @var string
	 */
	public string $non_unique_index = '';

	/**
	 * Change the lock value immediately before its conditional deletion.
	 *
	 * @var bool
	 */
	public bool $race_on_lock_delete = false;

	/**
	 * Arguments passed to the latest prepared query.
	 *
	 * @var array<int,mixed>
	 */
	private array $prepared_args = array();

	/**
	 * Return a deterministic collation for generated SQL.
	 *
	 * @return string Charset and collation SQL.
	 */
	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	/**
	 * Prepare a limited identifier query for the test double.
	 *
	 * @param string $query Query containing placeholders.
	 * @param mixed  ...$args Placeholder arguments.
	 * @return string Prepared query.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		$this->prepared_args = array_values( $args );

		if ( str_starts_with( $query, 'SHOW ' ) ) {
			return str_replace( '%i', '`' . (string) $args[0] . '`', $query );
		}

		return $query;
	}

	/**
	 * Conditionally delete only the lock value passed to prepare().
	 *
	 * @param string $query Prepared SQL.
	 * @return int Number of deleted option rows.
	 */
	public function query( string $query ): int {
		if ( ! str_starts_with( $query, 'DELETE FROM %i' ) ) {
			return 0;
		}

		if ( $this->race_on_lock_delete ) {
			$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::LOCK_OPTION ] =
				time() . ':replacement';
			$this->race_on_lock_delete = false;
		}

		$option_name = (string) $this->prepared_args[1];
		$value       = (string) $this->prepared_args[2];

		if (
			! isset( $GLOBALS['shurloc_test_options'][ $option_name ] ) ||
			$value !== $GLOBALS['shurloc_test_options'][ $option_name ]
		) {
			return 0;
		}

		unset( $GLOBALS['shurloc_test_options'][ $option_name ] );
		return 1;
	}

	/**
	 * Install the declaration named by a CREATE TABLE statement.
	 *
	 * @param string $sql dbDelta SQL.
	 * @return void
	 */
	public function install_table( string $sql ): void {
		if ( ! preg_match( '/^CREATE TABLE ([a-zA-Z0-9_]+) \(/', $sql, $matches ) ) {
			return;
		}

		$table_name   = $matches[1];
		$table_suffix = substr( $table_name, strlen( $this->prefix ) );
		$definitions  = Journey_Schema_V1::get_table_definitions();

		if ( isset( $definitions[ $table_suffix ] ) ) {
			$this->tables[ $table_name ] = $definitions[ $table_suffix ];
		}
	}

	/**
	 * Return simulated SHOW COLUMNS or SHOW INDEX rows.
	 *
	 * @param string $query Prepared schema query.
	 * @return array<int,object>|null Schema rows, or null for a missing table.
	 */
	public function get_results( string $query ): ?array {
		if ( ! preg_match( '/^SHOW (COLUMNS|INDEX) FROM `([a-zA-Z0-9_]+)`$/', $query, $matches ) ) {
			return null;
		}

		$table_name = $matches[2];

		if ( ! isset( $this->tables[ $table_name ] ) ) {
			return null;
		}

		$definition = $this->tables[ $table_name ];

		if ( 'COLUMNS' === $matches[1] ) {
			return $this->column_rows( columns: $definition['columns'] );
		}

		return $this->index_rows( indexes: $definition['indexes'] );
	}

	/**
	 * Build SHOW COLUMNS rows from the installed declaration.
	 *
	 * @param array<string,string> $columns Installed columns.
	 * @return array<int,object> Column rows.
	 * @throws RuntimeException When a test column declaration is invalid.
	 */
	private function column_rows( array $columns ): array {
		$rows = array();

		foreach ( $columns as $name => $sql ) {
			if ( $name === $this->missing_column ) {
				continue;
			}

			if ( ! preg_match( '/^[a-z]+(?:\([0-9,]+\))?(?: unsigned)?/', $sql, $matches ) ) {
				throw new RuntimeException( 'Invalid test column definition.' );
			}

			$rows[] = (object) array(
				'Field' => $name,
				'Type'  => $matches[0],
				'Null'  => str_contains( $sql, 'DEFAULT NULL' ) ? 'YES' : 'NO',
				'Extra' => str_contains( $sql, 'AUTO_INCREMENT' ) ? 'auto_increment' : '',
			);
		}

		return $rows;
	}

	/**
	 * Build SHOW INDEX rows from the installed declaration.
	 *
	 * @param array<string,array<string,mixed>> $indexes Installed indexes.
	 * @return array<int,object> Index rows.
	 */
	private function index_rows( array $indexes ): array {
		$rows = array();

		foreach ( $indexes as $name => $index ) {
			if ( $name === $this->missing_index ) {
				continue;
			}

			foreach ( $index['columns'] as $position => $column_name ) {
				$rows[] = (object) array(
					'Key_name'     => $name,
					'Column_name'  => $column_name,
					'Seq_in_index' => $position + 1,
					'Non_unique'   => $index['unique'] && $name !== $this->non_unique_index ? 0 : 1,
				);
			}
		}

		return $rows;
	}
}

	/**
	 * Tests migration versioning, locking, and verification.
	 */
final class JourneySchemaMigratorTest extends TestCase {
	/**
	 * Database double.
	 *
	 * @var Shurloc_Journey_Schema_Test_WPDB
	 */
	private Shurloc_Journey_Schema_Test_WPDB $database;

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

		$this->database = new Shurloc_Journey_Schema_Test_WPDB();

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
	 * Verify a fresh install creates and verifies every table.
	 *
	 * @return void
	 */
	public function test_fresh_install_creates_four_tables_and_stores_version(): void {
		$migrator = $this->create_migrator();

		self::assertTrue( $migrator->migrate() );
		self::assertSame( 1, $migrator->get_installed_version() );
		self::assertTrue( $migrator->is_ready() );
		self::assertCount( 4, $GLOBALS['shurloc_journey_dbdelta_calls'] );
		self::assertSame(
			array(
				'wp_shurloc_journey_visitors',
				'wp_shurloc_journey_identity_periods',
				'wp_shurloc_journey_sessions',
				'wp_shurloc_journey_events',
			),
			array_keys( $this->database->tables )
		);
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
		self::assertSame( 1, $migrator->get_installed_version() );
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
		self::assertSame( 1, $migrator->get_installed_version() );
		self::assertArrayNotHasKey(
			Journey_Schema_Migrator::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
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
		self::assertStringStartsWith(
			'CREATE TABLE store_42_shurloc_journey_visitors',
			$GLOBALS['shurloc_journey_dbdelta_calls'][0]
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
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 2;
		$migrator = $this->create_migrator();

		self::assertFalse( $migrator->migrate() );
		self::assertSame( 2, $migrator->get_installed_version() );
		self::assertFalse( $migrator->is_ready() );
		self::assertSame( 'newer_schema', $migrator->get_failure_code() );
		self::assertSame( array(), $GLOBALS['shurloc_journey_dbdelta_calls'] );
	}
}
