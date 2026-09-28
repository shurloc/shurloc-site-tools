<?php
/**
 * Customer Journey database schema migrator.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

defined( 'ABSPATH' ) || exit;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Applies sequential Journey schema versions without running on page views.
 */
final class Journey_Schema_Migrator {

	/**
	 * Current Journey database schema version, independent of plugin version.
	 */
	public const CURRENT_VERSION = 3;

	/**
	 * Installed Journey schema version option.
	 */
	public const VERSION_OPTION = 'shurloc_customer_journey_db_version';

	/**
	 * Migration lock option.
	 */
	public const LOCK_OPTION = 'shurloc_customer_journey_schema_lock';

	/**
	 * Last migration failure code for a later admin notice and retry controller.
	 */
	public const FAILURE_OPTION = 'shurloc_customer_journey_schema_failure';

	/** Dedicated append-only migration diagnostic log. */
	public const LOG_FILENAME = 'shurloc_journey_migration.log';

	/**
	 * Time after which an abandoned migration lock may be reclaimed.
	 */
	private const LOCK_TIMEOUT = 900;

	/**
	 * The lock value owned by this migrator instance.
	 *
	 * @var string|null
	 */
	private ?string $lock_value = null;

	/**
	 * Runs WordPress dbDelta for one statement.
	 *
	 * @var Closure(string):void
	 */
	private Closure $schema_updater;

	/**
	 * Writes one diagnostic log entry.
	 *
	 * @var Closure(string):void
	 */
	private Closure $failure_logger;

	/**
	 * Constructor.
	 *
	 * @param Closure(string):void|null $schema_updater Schema update callback.
	 * @param Closure(string):void|null $failure_logger Migration failure logger.
	 */
	public function __construct( ?Closure $schema_updater = null, ?Closure $failure_logger = null ) {
		$this->schema_updater = $schema_updater ?? static function ( string $statement ): void {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			dbDelta( $statement );
		};
		$this->failure_logger = $failure_logger ?? static function ( string $entry ): void {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- A dedicated migration log is required for failures that disable the feature.
			error_log( $entry . PHP_EOL, 3, self::get_log_path() );
		};
	}

	/**
	 * Return the dedicated migration diagnostic log path.
	 *
	 * @return string Absolute log path.
	 */
	public static function get_log_path(): string {
		$content_directory = defined( 'WP_CONTENT_DIR' )
			? (string) constant( 'WP_CONTENT_DIR' )
			: ABSPATH . 'wp-content';

		return rtrim( $content_directory, '/\\' ) . DIRECTORY_SEPARATOR . self::LOG_FILENAME;
	}

	/**
	 * Get the installed schema version.
	 *
	 * @return int Installed version, or zero before installation.
	 */
	public function get_installed_version(): int {
		return max( 0, (int) get_option( self::VERSION_OPTION, 0 ) );
	}

	/**
	 * Determine whether all Journey schema-dependent code may run.
	 *
	 * @return bool True only when this code's schema version is installed.
	 */
	public function is_ready(): bool {
		return self::CURRENT_VERSION === $this->get_installed_version();
	}

	/**
	 * Get a generic, non-sensitive failure code for administration notices.
	 *
	 * @return string Empty when no failure has been recorded.
	 */
	public function get_failure_code(): string {
		$code = get_option( self::FAILURE_OPTION, '' );

		return is_string( $code ) ? $code : '';
	}

	/**
	 * Calculate the ascending versions that an installation must apply.
	 *
	 * @param int $installed_version Installed schema version.
	 * @param int $target_version    Version supplied by the running code.
	 * @return array<int,int> Pending versions in order.
	 */
	public static function get_pending_versions(
		int $installed_version,
		int $target_version
	): array {
		if ( $installed_version >= $target_version ) {
			return array();
		}

		return range( max( 1, $installed_version + 1 ), $target_version );
	}

	/**
	 * Upgrade the schema when needed, leaving the version unchanged on failure.
	 *
	 * @return bool True when the current schema is ready.
	 * @throws RuntimeException If lock release fails outside migration handling.
	 */
	public function migrate(): bool {
		$installed_version = $this->get_installed_version();
		$attempted_version = null;

		if ( self::CURRENT_VERSION === $installed_version ) {
			return true;
		}

		if ( self::CURRENT_VERSION < $installed_version ) {
			update_option( self::FAILURE_OPTION, 'newer_schema' );
			return false;
		}

		try {
			if ( ! $this->acquire_lock() ) {
				return false;
			}

			// Recheck after acquiring the lock in case another request upgraded first.
			$installed_version = $this->get_installed_version();

			foreach (
				self::get_pending_versions(
					installed_version: $installed_version,
					target_version: self::CURRENT_VERSION,
				) as $version
			) {
				$attempted_version = $version;
				$this->apply_version( version: $version );

				if ( ! update_option( self::VERSION_OPTION, $version ) &&
					$version !== $this->get_installed_version() ) {
					throw new RuntimeException( 'Journey schema version could not be stored.' );
				}
			}

			delete_option( self::FAILURE_OPTION );

			return $this->is_ready();
		} catch ( Throwable $error ) {
			$this->log_failure(
				error: $error,
				installed_version: $installed_version,
				attempted_version: $attempted_version,
			);
			update_option( self::FAILURE_OPTION, 'migration_failed' );
			return false;
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Write a structured failure record without changing migration handling.
	 *
	 * Logging failures are deliberately ignored so the failure option, version,
	 * and lock behavior remain authoritative.
	 *
	 * @param Throwable $error             Migration failure.
	 * @param int       $installed_version Version installed before this attempt.
	 * @param int|null  $attempted_version Version being applied when it failed.
	 * @return void
	 */
	private function log_failure( Throwable $error, int $installed_version, ?int $attempted_version ): void {
		global $wpdb;

		$database_error = $wpdb->last_error ?? '';
		$entry          = wp_json_encode(
			array(
				'event'             => 'journey_schema_migration_failed',
				'timestamp_utc'     => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'installed_version' => $installed_version,
				'attempted_version' => $attempted_version,
				'target_version'    => self::CURRENT_VERSION,
				'exception_class'   => $error::class,
				'exception_message' => $error->getMessage(),
				'database_error'    => is_string( $database_error ) ? $database_error : '',
			),
			JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
		);

		if ( ! is_string( $entry ) ) {
			return;
		}

		try {
			( $this->failure_logger )( $entry );
		} catch ( Throwable $logging_error ) {
			unset( $logging_error );
		}
	}

	/**
	 * Acquire an atomic option lock, reclaiming only an unchanged stale value.
	 *
	 * @return bool True when this instance owns the lock.
	 */
	private function acquire_lock(): bool {
		$this->lock_value = time() . ':' . bin2hex( random_bytes( 16 ) );

		if ( add_option( self::LOCK_OPTION, $this->lock_value, '', false ) ) {
			return true;
		}

		$existing_value = get_option( self::LOCK_OPTION, '' );

		if ( ! is_string( $existing_value ) ) {
			$this->lock_value = null;
			return false;
		}

		$locked_at = (int) strtok( $existing_value, ':' );

		if ( 0 >= $locked_at || time() - $locked_at <= self::LOCK_TIMEOUT ) {
			$this->lock_value = null;
			return false;
		}

		if ( ! $this->delete_lock_value( value: $existing_value ) ) {
			$this->lock_value = null;
			return false;
		}

		if ( ! add_option( self::LOCK_OPTION, $this->lock_value, '', false ) ) {
			$this->lock_value = null;
			return false;
		}

		return true;
	}

	/**
	 * Release the lock only when its stored value still belongs to this instance.
	 *
	 * @return void
	 */
	private function release_lock(): void {
		if ( null === $this->lock_value ) {
			return;
		}

		$this->delete_lock_value( value: $this->lock_value );
		$this->lock_value = null;
	}

	/**
	 * Delete a matching option value atomically and clear its option cache entry.
	 *
	 * @param string $value Value that must still own the lock.
	 * @return bool True when that exact value was deleted.
	 * @phpstan-impure
	 */
	private function delete_lock_value( string $value ): bool {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name = %s AND option_value = %s',
				$wpdb->options,
				self::LOCK_OPTION,
				$value
			)
		);

		if ( 1 !== $deleted ) {
			return false;
		}

		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::LOCK_OPTION, 'options' );
		}

		return true;
	}

	/**
	 * Apply one version and verify it before the caller stores that version.
	 *
	 * @param int $version Schema version to apply.
	 * @return void
	 * @throws RuntimeException When the version is unknown or verification fails.
	 */
	private function apply_version( int $version ): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		switch ( $version ) {
			case 1:
				$statements  = Journey_Schema_V1::get_create_table_statements(
					table_prefix: $wpdb->prefix,
					charset_collate: $charset_collate,
				);
				$definitions = Journey_Schema_V1::get_table_definitions();
				break;

			case 2:
				$statements  = Journey_Schema_V2::get_create_table_statements(
					table_prefix: $wpdb->prefix,
					charset_collate: $charset_collate,
				);
				$definitions = array_merge(
					Journey_Schema_V1::get_table_definitions(),
					Journey_Schema_V2::get_table_definitions(),
				);
				break;

			case 3:
				$statements  = Journey_Schema_V3::get_create_table_statements(
					table_prefix: $wpdb->prefix,
					charset_collate: $charset_collate,
				);
				$definitions = array_merge(
					Journey_Schema_V1::get_table_definitions(),
					Journey_Schema_V2::get_table_definitions(),
					Journey_Schema_V3::get_table_definitions(),
				);
				break;

			default:
				throw new RuntimeException( 'Unknown Journey schema version.' );
		}

		foreach ( $statements as $statement ) {
			( $this->schema_updater )( $statement );
		}

		$this->verify_schema(
			table_prefix: $wpdb->prefix,
			definitions: $definitions,
		);

		if ( 3 === $version ) {
			$this->backfill_event_count();
		}
	}

	/**
	 * Populate the new total-event counter from the complete v1 summaries.
	 *
	 * Product views already contribute to page_view_count, so adding the
	 * product-specific counter would count those events twice. The update is
	 * idempotent and may safely run again after an interrupted migration.
	 *
	 * @return void
	 * @throws RuntimeException When the session summaries cannot be updated.
	 */
	private function backfill_event_count(): void {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET event_count = page_view_count + cart_add_count + cart_remove_count + checkout_started_count + order_created_count WHERE event_count <> page_view_count + cart_add_count + cart_remove_count + checkout_started_count + order_created_count',
				$wpdb->prefix . 'shurloc_journey_sessions'
			)
		);

		if ( false === $updated ) {
			throw new RuntimeException( 'Journey event totals could not be backfilled.' );
		}
	}

	/**
	 * Verify every required column, type, index, and transactional storage engine.
	 *
	 * @param string              $table_prefix WordPress database prefix.
	 * @param array<string,mixed> $definitions  Schema definitions.
	 * @return void
	 * @throws RuntimeException When a required schema element is unavailable.
	 */
	private function verify_schema(
		string $table_prefix,
		array $definitions
	): void {
		global $wpdb;

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal diagnostics are JSON-encoded for the dedicated log, not rendered as HTML.
		foreach ( $definitions as $suffix => $definition ) {
			if ( ! is_array( $definition ) ) {
				throw new RuntimeException( 'Invalid Journey schema definition for "' . $suffix . '".' );
			}

			$table_name = $table_prefix . $suffix;
			$status     = $wpdb->get_results(
				$wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table_name )
			);

			if (
				! is_array( $status ) ||
				1 !== count( $status ) ||
				! isset( $status[0]->Name, $status[0]->Engine ) ||
				$table_name !== $status[0]->Name
			) {
				throw new RuntimeException( 'Journey schema table "' . $table_name . '" is unavailable.' );
			}

			if ( 'InnoDB' !== $status[0]->Engine ) {
				throw new RuntimeException(
					'Journey schema table "' . $table_name . '" must use InnoDB; found "' . $status[0]->Engine . '".'
				);
			}

			$columns = $wpdb->get_results(
				$wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name )
			);
			$indexes = $wpdb->get_results(
				$wpdb->prepare( 'SHOW INDEX FROM %i', $table_name )
			);

			if ( ! is_array( $columns ) || ! is_array( $indexes ) ) {
				throw new RuntimeException( 'Journey schema table "' . $table_name . '" cannot be inspected.' );
			}

			$this->verify_columns(
				table_name: $table_name,
				actual_rows: $columns,
				expected_columns: $definition['columns'],
			);
			$this->verify_indexes(
				table_name: $table_name,
				actual_rows: $indexes,
				expected_indexes: $definition['indexes'],
			);
		}
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Verify the table's required columns and SQL types.
	 *
	 * @param string               $table_name       Full table name.
	 * @param array<int,object>    $actual_rows      SHOW COLUMNS rows.
	 * @param array<string,string> $expected_columns Expected column definitions.
	 * @return void
	 * @throws RuntimeException When a column is missing or incompatible.
	 */
	private function verify_columns(
		string $table_name,
		array $actual_rows,
		array $expected_columns
	): void {
		$actual = array();

		foreach ( $actual_rows as $row ) {
			$column = (array) $row;

			if ( isset( $column['Field'] ) && is_string( $column['Field'] ) ) {
				$actual[ $column['Field'] ] = $column;
			}
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal diagnostics are JSON-encoded for the dedicated log, not rendered as HTML.
		foreach ( $expected_columns as $name => $definition ) {
			$column = $actual[ $name ] ?? null;

			if ( null === $column || ! isset( $column['Type'], $column['Null'] ) ) {
				throw new RuntimeException( 'Journey schema column "' . $table_name . '.' . $name . '" is unavailable.' );
			}

			$expected_type = $this->get_expected_type( definition: $definition );

			if (
				! is_string( $column['Type'] ) ||
				$this->normalize_type( type: $column['Type'] ) !==
					$this->normalize_type( type: $expected_type ) ||
				( str_contains( $definition, 'DEFAULT NULL' ) ? 'YES' : 'NO' ) !== $column['Null'] ||
				( str_contains( $definition, 'AUTO_INCREMENT' ) &&
					! str_contains( (string) ( $column['Extra'] ?? '' ), 'auto_increment' ) )
			) {
				throw new RuntimeException(
					'Journey schema column "' . $table_name . '.' . $name . '" is incompatible with expected definition "' .
					$definition . '"; found type "' . (string) $column['Type'] . '", NULL "' .
					(string) $column['Null'] . '", Extra "' . (string) ( $column['Extra'] ?? '' ) . '".'
				);
			}
		}
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Verify index column order and uniqueness.
	 *
	 * @param string              $table_name       Full table name.
	 * @param array<int,object>   $actual_rows      SHOW INDEX rows.
	 * @param array<string,mixed> $expected_indexes Expected index definitions.
	 * @return void
	 * @throws RuntimeException When an index is missing or incompatible.
	 */
	private function verify_indexes(
		string $table_name,
		array $actual_rows,
		array $expected_indexes
	): void {
		$actual = array();

		foreach ( $actual_rows as $row ) {
			$index = (array) $row;

			if (
				! isset( $index['Key_name'], $index['Column_name'], $index['Seq_in_index'], $index['Non_unique'] ) ||
				! is_string( $index['Key_name'] ) ||
				! is_string( $index['Column_name'] )
			) {
				continue;
			}

			$actual[ $index['Key_name'] ]['columns'][ (int) $index['Seq_in_index'] ] = $index['Column_name'];
			$actual[ $index['Key_name'] ]['unique']                                  = 0 === (int) $index['Non_unique'];
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal diagnostics are JSON-encoded for the dedicated log, not rendered as HTML.
		foreach ( $expected_indexes as $name => $definition ) {
			if ( ! is_array( $definition ) || ! isset( $actual[ $name ] ) ) {
				throw new RuntimeException( 'Journey schema index "' . $table_name . '.' . $name . '" is unavailable.' );
			}

			$columns = $actual[ $name ]['columns'];
			ksort( $columns );

			if (
				array_values( $columns ) !== $definition['columns'] ||
				$actual[ $name ]['unique'] !== $definition['unique']
			) {
				throw new RuntimeException( 'Journey schema index "' . $table_name . '.' . $name . '" is incompatible.' );
			}
		}
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Extract the SQL type from a column definition.
	 *
	 * @param string $definition Column definition.
	 * @return string Column SQL type.
	 * @throws RuntimeException When the declaration has no SQL type.
	 */
	private function get_expected_type( string $definition ): string {
		if ( ! preg_match( '/^[a-z]+(?:\([0-9,]+\))?(?: unsigned)?/', $definition, $matches ) ) {
			throw new RuntimeException( 'Invalid Journey column definition.' );
		}

		return $matches[0];
	}

	/**
	 * Ignore display widths that MySQL may omit for standard integer columns.
	 *
	 * @param string $type SQL type.
	 * @return string Normalized SQL type.
	 */
	private function normalize_type( string $type ): string {
		$normalized = preg_replace(
			'/\b(bigint|int|mediumint|smallint|tinyint)\([0-9]+\)/',
			'$1',
			strtolower( trim( $type ) )
		);

		return is_string( $normalized ) ? $normalized : '';
	}
}
